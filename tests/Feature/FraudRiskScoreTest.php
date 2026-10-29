<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Actions\AddToCartAction;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use App\Modules\Order\Actions\CalculateOrderFraudRiskScoreAction;
use App\Modules\Order\Actions\CreateOrderFromCartAction;
use App\Modules\Order\DTOs\OrderFraudRiskAssessmentDto;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class FraudRiskScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    private ProductVariant $variant;

    private CalculateOrderFraudRiskScoreAction $calculateFraudScore;

    private HoneypotTimingGuardService $honeypotGuard;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'services.steadfast.api_key' => 'test_sf_api_key',
            'services.steadfast.secret_key' => 'test_sf_secret_key',
            'services.pathao.token' => 'test_pathao_token',
        ]);

        $this->user = User::factory()->create();

        $this->division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA',
            'is_active' => true,
        ]);

        $this->district = BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-01',
            'zone_type' => 'inside_dhaka',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Anker',
            'slug' => 'anker',
            'country_of_origin' => 'CN',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Anker Soundcore Earbuds',
            'slug' => 'anker-soundcore-earbuds',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ANK-EAR-001',
            'barcode_ean' => '848061000001',
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '2500.0000',
            'selling_price_bdt' => '3500.0000',
            'is_active' => true,
        ]);

        $this->calculateFraudScore = app(CalculateOrderFraudRiskScoreAction::class);
        $this->honeypotGuard = app(HoneypotTimingGuardService::class);
    }

    public function test_clean_order_evaluates_to_low_risk_and_allows_cod(): void
    {
        $timingToken = $this->honeypotGuard->generateTimingToken(microtime(true) - 10.0);

        $assessment = ($this->calculateFraudScore)([
            'customer_name' => 'Tanvir Hossain',
            'customer_phone' => '01712398765',
            'delivery_address' => 'House 12, Road 5, Dhanmondi, Dhaka',
            'payment_method' => 'cash_on_delivery',
            'ip_address' => '103.145.12.4',
            'timing_token' => $timingToken,
            'skip_courier_lookup' => true,
        ]);

        $this->assertEquals(0, $assessment->score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_LOW, $assessment->riskLevel);
        $this->assertTrue($assessment->isCodAllowed);
        $this->assertFalse($assessment->requiresOtp);
        $this->assertFalse($assessment->requiresAdvanceDeliveryCharge);
        $this->assertEmpty($assessment->reasons);
    }

    public function test_honeypot_trap_instantly_triggers_blacklisted_level(): void
    {
        $assessment = ($this->calculateFraudScore)([
            'customer_name' => 'Bot Spammer',
            'customer_phone' => '01712398765',
            'delivery_address' => 'Road 1, Dhaka',
            'user_company_fax' => 'Automated Spambot Value',
            'skip_courier_lookup' => true,
        ]);

        $this->assertEquals(100, $assessment->score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_BLACKLISTED, $assessment->riskLevel);
        $this->assertFalse($assessment->isCodAllowed);
        $this->assertTrue($assessment->signals['honeypot']['triggered']);
        $this->assertStringContainsString('honeypot', $assessment->reasons[0]);
    }

    public function test_fast_submission_timing_and_dummy_phone_evaluates_to_high_risk(): void
    {
        // 1 second elapsed (< 3.5s threshold)
        $fastTimingToken = $this->honeypotGuard->generateTimingToken(microtime(true) - 1.0);

        $assessment = ($this->calculateFraudScore)([
            'customer_name' => 'Rahim Ali',
            'customer_phone' => '01711111111', // Dummy repeating sequence
            'delivery_address' => 'House 5, Road 2, Gulshan, Dhaka',
            'payment_method' => 'cash_on_delivery',
            'timing_token' => $fastTimingToken,
            'skip_courier_lookup' => true,
        ]);

        // Timing (+50) + Dummy phone (+50) = 100
        $this->assertEquals(100, $assessment->score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_BLACKLISTED, $assessment->riskLevel);
        $this->assertFalse($assessment->isCodAllowed);
    }

    public function test_gibberish_name_and_address_raises_fraud_score_to_medium(): void
    {
        $timingToken = $this->honeypotGuard->generateTimingToken(microtime(true) - 10.0);

        $assessment = ($this->calculateFraudScore)([
            'customer_name' => 'asdfghjkl', // Keyboard mash (+25)
            'customer_phone' => '01712398765',
            'delivery_address' => 'no address', // Dummy keyword (+30)
            'payment_method' => 'cash_on_delivery',
            'timing_token' => $timingToken,
            'skip_courier_lookup' => true,
        ]);

        $this->assertEquals(55, $assessment->score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_MEDIUM, $assessment->riskLevel);
        $this->assertTrue($assessment->isCodAllowed);
        $this->assertTrue($assessment->requiresOtp);
        $this->assertFalse($assessment->requiresAdvanceDeliveryCharge);
        $this->assertCount(2, $assessment->reasons);
    }

    public function test_high_courier_return_rate_flags_high_risk_and_disables_cod(): void
    {
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'delivery_status' => [
                    'total_parcels' => 10,
                    'delivered' => 4,
                    'cancelled' => 6, // 60% return rate (> 40%)
                ],
            ], 200),
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'data' => [
                    'total_orders' => 0,
                ],
            ], 200),
        ]);

        $assessment = ($this->calculateFraudScore)([
            'customer_name' => 'Shakil Ahmed',
            'customer_phone' => '01712398765',
            'delivery_address' => 'House 42, Road 11, Banani, Dhaka',
            'payment_method' => 'cash_on_delivery',
            'skip_courier_lookup' => false,
        ]);

        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_HIGH, $assessment->riskLevel);
        $this->assertFalse($assessment->isCodAllowed);
        $this->assertTrue($assessment->requiresAdvanceDeliveryCharge);
        $this->assertTrue($assessment->requiresOtp);
        $this->assertStringContainsString('courier return rate is critically high', $assessment->reasons[0]);
    }

    public function test_create_order_action_persists_fraud_score_and_enforces_cod_rules(): void
    {
        $getCart = app(GetActiveCartAction::class);
        $addToCart = app(AddToCartAction::class);
        $createOrder = app(CreateOrderFromCartAction::class);

        // 1. Clean order creates successfully with low risk
        $cart1 = $getCart(user: $this->user);
        $addToCart(cart: $cart1, variant: $this->variant, quantity: 1);

        $order1 = $createOrder(
            cart: $cart1,
            customerData: [
                'customer_name' => 'Valid Customer',
                'customer_phone' => '01712398765',
                'division_id' => $this->division->id,
                'district_id' => $this->district->id,
                'delivery_address' => 'House 20, Road 4, Dhanmondi, Dhaka',
                'payment_method' => 'cash_on_delivery',
                'skip_courier_lookup' => true,
            ],
            user: $this->user
        );

        $this->assertEquals(0, $order1->fraud_risk_score);
        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_LOW, $order1->fraud_risk_level);

        // 2. Blacklisted submission (honeypot filled) throws ValidationException
        $cart2 = $getCart(user: $this->user);
        $addToCart(cart: $cart2, variant: $this->variant, quantity: 1);

        try {
            $createOrder(
                cart: $cart2,
                customerData: [
                    'customer_name' => 'Spammer',
                    'customer_phone' => '01712398765',
                    'division_id' => $this->division->id,
                    'district_id' => $this->district->id,
                    'delivery_address' => 'Dhaka',
                    'user_company_fax' => 'bot_trap_value',
                    'skip_courier_lookup' => true,
                ],
                user: $this->user
            );
            $this->fail('Expected ValidationException for honeypot bot trap');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fraud', $e->errors());
        }

        // 3. High-risk COD submission (courier return rate > 40%) blocks COD
        Http::fake([
            'https://ssl.steadfast.com.bd/api/v1/fraud-check/*' => Http::response([
                'delivery_status' => [
                    'total_parcels' => 10,
                    'delivered' => 3,
                    'cancelled' => 7,
                ],
            ], 200),
            'https://courier.pathao.com/aladdin/api/v1/user/success-rate*' => Http::response([
                'data' => ['total_orders' => 0],
            ], 200),
        ]);

        $cart3 = $getCart(user: $this->user);
        $addToCart(cart: $cart3, variant: $this->variant, quantity: 1);

        try {
            $createOrder(
                cart: $cart3,
                customerData: [
                    'customer_name' => 'High Return Shopper',
                    'customer_phone' => '01819874563',
                    'division_id' => $this->division->id,
                    'district_id' => $this->district->id,
                    'delivery_address' => 'House 1, Road 2, Dhaka',
                    'payment_method' => 'cash_on_delivery',
                    'skip_courier_lookup' => false,
                ],
                user: $this->user
            );
            $this->fail('Expected ValidationException for high return rate COD order');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method', $e->errors());
            $this->assertStringContainsString('Cash on Delivery is unavailable', $e->errors()['payment_method'][0]);
        }

        // 4. Same high-risk customer can order using an online prepaid method (e.g. bKash)!
        $order3 = $createOrder(
            cart: $cart3,
            customerData: [
                'customer_name' => 'High Return Shopper',
                'customer_phone' => '01819874563',
                'division_id' => $this->division->id,
                'district_id' => $this->district->id,
                'delivery_address' => 'House 1, Road 2, Dhaka',
                'payment_method' => 'bkash',
                'skip_courier_lookup' => false,
            ],
            user: $this->user
        );

        $this->assertEquals(OrderFraudRiskAssessmentDto::LEVEL_HIGH, $order3->fraud_risk_level);
        $this->assertEquals('bkash', $order3->payment_method);
    }
}
