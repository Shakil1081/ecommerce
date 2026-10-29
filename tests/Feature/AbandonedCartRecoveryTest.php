<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Communication\Models\SmsGatewaySetting;
use App\Modules\Marketing\Actions\MarkCartAsRecoveredAction;
use App\Modules\Marketing\Actions\ProcessCartRecoverySequencesAction;
use App\Modules\Marketing\Models\CartRecoveryLog;
use App\Modules\Order\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbandonedCartRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function createDummyCartWithItem(string $phone = '01712345678', ?Carbon $lastActivity = null): Cart
    {
        $category = Category::create([
            'name' => 'Audio & Sound',
            'slug' => 'audio-sound-'.uniqid(),
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'ANC Wireless Earbuds',
            'slug' => 'anc-earbuds-'.uniqid(),
            'sku' => 'SKU-EARBUDS-'.uniqid(),
            'status' => 'published',
            'pricing_type' => 'simple',
            'retail_price_bdt' => 2500.00,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-EARBUDS-'.uniqid(),
            'attributes' => ['color' => 'Black'],
            'selling_price_bdt' => 2500.00,
            'purchase_cost_bdt' => 1200.00,
            'stock_quantity' => 50,
        ]);

        $cart = Cart::create([
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => $phone,
            'customer_email' => 'shakil@example.com',
            'status' => 'active',
            'subtotal_bdt' => 2500.00,
            'total_bdt' => 2500.00,
            'total_items_count' => 1,
            'last_activity_at' => $lastActivity ?? now()->subMinutes(75),
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price_bdt' => 2500.00,
            'subtotal_bdt' => 2500.00,
        ]);

        return $cart;
    }

    private function createDummyOrder(): Order
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা']);
        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => 'inside_dhaka',
        ]);

        return Order::create([
            'order_number' => 'ORD-20261029-REC01',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Mirpur 10, Dhaka',
            'subtotal_bdt' => 2430.00,
            'shipping_fee_bdt' => 70.00,
            'total_bdt' => 2500.00,
            'payable_cod_amount_bdt' => 2500.00,
        ]);
    }

    public function test_process_cart_recovery_ignores_empty_or_converted_carts(): void
    {
        // 1. Empty cart
        Cart::create([
            'customer_phone' => '01711223344',
            'status' => 'active',
            'total_items_count' => 0,
            'last_activity_at' => now()->subHours(2),
        ]);

        // 2. Converted cart
        $cart2 = $this->createDummyCartWithItem('01722334455', now()->subHours(2));
        $cart2->update(['status' => 'converted']);

        // 3. Cart without phone
        $cart3 = $this->createDummyCartWithItem('', now()->subHours(2));
        $cart3->update(['customer_phone' => null]);

        $action = app(ProcessCartRecoverySequencesAction::class);
        $result = $action();

        $this->assertEquals(0, $result['total_dispatched']);
        $this->assertEquals(0, CartRecoveryLog::count());
    }

    public function test_process_cart_recovery_dispatches_step_1_at_1_hour(): void
    {
        SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'credentials' => ['token' => 'test_token'],
        ]);

        Http::fake([
            'https://api.greenweb.com.bd/api.php' => Http::response('Ok: SMS Sent 1', 200),
        ]);

        $cart = $this->createDummyCartWithItem('01712345678', now()->subMinutes(75));

        $action = app(ProcessCartRecoverySequencesAction::class);
        $result = $action();

        $this->assertEquals(1, $result['step_1_count']);
        $this->assertEquals(1, $result['total_dispatched']);

        $cart->refresh();
        $this->assertEquals('abandoned', $cart->status);

        $log = CartRecoveryLog::where('cart_id', $cart->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(CartRecoveryLog::STEP_ONE, $log->step);
        $this->assertEquals('01712345678', $log->customer_phone);
        $this->assertEquals(CartRecoveryLog::STATUS_SENT, $log->status);
        $this->assertStringContainsString('left items in your shopping cart', $log->message_content);
    }

    public function test_process_cart_recovery_dispatches_step_2_at_6_hours(): void
    {
        SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'credentials' => ['token' => 'test_token'],
        ]);

        Http::fake([
            'https://api.greenweb.com.bd/api.php' => Http::response('Ok: SMS Sent 1', 200),
        ]);

        // Cart abandoned 7 hours ago with existing step 1 log
        $cart = $this->createDummyCartWithItem('01712345678', now()->subHours(7));
        CartRecoveryLog::create([
            'cart_id' => $cart->id,
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'step' => CartRecoveryLog::STEP_ONE,
            'channel' => 'sms',
            'status' => 'sent',
            'dispatched_at' => now()->subHours(6),
        ]);

        $action = app(ProcessCartRecoverySequencesAction::class);
        $result = $action();

        $this->assertEquals(1, $result['step_2_count']);
        $this->assertEquals(1, $result['total_dispatched']);

        $step2Log = CartRecoveryLog::where('cart_id', $cart->id)->where('step', 2)->first();
        $this->assertNotNull($step2Log);
        $this->assertStringContainsString('running low', $step2Log->message_content);
    }

    public function test_process_cart_recovery_dispatches_step_3_at_24_hours_with_incentive_coupon(): void
    {
        SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'credentials' => ['token' => 'test_token'],
        ]);

        Http::fake([
            'https://api.greenweb.com.bd/api.php' => Http::response('Ok: SMS Sent 1', 200),
        ]);

        // Cart abandoned 26 hours ago with step 1 and step 2 logs
        $cart = $this->createDummyCartWithItem('01712345678', now()->subHours(26));
        CartRecoveryLog::create([
            'cart_id' => $cart->id,
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'step' => CartRecoveryLog::STEP_ONE,
            'channel' => 'sms',
            'status' => 'sent',
            'dispatched_at' => now()->subHours(25),
        ]);
        CartRecoveryLog::create([
            'cart_id' => $cart->id,
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'step' => CartRecoveryLog::STEP_TWO,
            'channel' => 'sms',
            'status' => 'sent',
            'dispatched_at' => now()->subHours(20),
        ]);

        $action = app(ProcessCartRecoverySequencesAction::class);
        $result = $action();

        $this->assertEquals(1, $result['step_3_count']);
        $this->assertEquals(1, $result['total_dispatched']);

        $step3Log = CartRecoveryLog::where('cart_id', $cart->id)->where('step', 3)->first();
        $this->assertNotNull($step3Log);
        $this->assertEquals('COMEBACK5', $step3Log->incentive_coupon_code);
        $this->assertStringContainsString('COMEBACK5', $step3Log->message_content);
    }

    public function test_mark_cart_as_recovered_action_updates_logs(): void
    {
        $cart = $this->createDummyCartWithItem('01712345678', now()->subHours(2));
        $log1 = CartRecoveryLog::create([
            'cart_id' => $cart->id,
            'customer_name' => 'Kazi Shakil',
            'customer_phone' => '01712345678',
            'step' => CartRecoveryLog::STEP_ONE,
            'channel' => 'sms',
            'status' => 'sent',
            'dispatched_at' => now()->subHours(1),
        ]);

        $order = $this->createDummyOrder();

        $action = app(MarkCartAsRecoveredAction::class);
        $action($cart, $order);

        $cart->refresh();
        $this->assertEquals('converted', $cart->status);

        $log1->refresh();
        $this->assertEquals(CartRecoveryLog::STATUS_RECOVERED, $log1->status);
        $this->assertEquals($order->id, $log1->recovered_order_id);
        $this->assertNotNull($log1->recovered_at);
    }

    public function test_cart_process_recovery_artisan_command(): void
    {
        SmsGatewaySetting::create([
            'code' => SmsGatewaySetting::CODE_GREENWEB,
            'name' => 'Greenweb SMS',
            'is_active' => true,
            'credentials' => ['token' => 'test_token'],
        ]);

        Http::fake([
            'https://api.greenweb.com.bd/api.php' => Http::response('Ok: SMS Sent 1', 200),
        ]);

        $this->createDummyCartWithItem('01712345678', now()->subMinutes(80));

        $this->artisan('cart:process-recovery')
            ->expectsOutputToContain('Scanning abandoned shopping carts...')
            ->expectsOutputToContain('Total Recovery Messages Sent')
            ->assertExitCode(0);

        $this->assertEquals(1, CartRecoveryLog::count());
    }
}
