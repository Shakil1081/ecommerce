<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Customer\Services\TextEntropyService;
use App\Modules\Order\Actions\CheckOrderVelocityAction;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class CustomerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    protected function setUp(): void
    {
        parent::setUp();

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
            'code' => 'DHAKA',
            'zone_type' => 'inside_dhaka',
            'is_active' => true,
        ]);
    }

    public function test_bangladesh_phone_number_rule_accepts_valid_operator_numbers(): void
    {
        $validCases = [
            '01712398765' => 'Grameenphone',
            '01315987423' => 'Grameenphone',
            '01918742365' => 'Banglalink',
            '01405987412' => 'Banglalink',
            '01815984726' => 'Robi / Airtel',
            '01614785236' => 'Robi / Airtel',
            '01552345987' => 'Teletalk',
        ];

        foreach ($validCases as $phone => $expectedOperator) {
            $this->assertTrue(BangladeshPhoneNumberRule::isValid($phone), "Failed asserting valid for {$phone}");
            $this->assertEquals($expectedOperator, BangladeshPhoneNumberRule::getOperator($phone));
            $this->assertEquals($phone, BangladeshPhoneNumberRule::toLocalFormat($phone));
            $this->assertEquals('+88'.$phone, BangladeshPhoneNumberRule::toE164Format($phone));
        }

        // Test with country code prefixes
        $this->assertTrue(BangladeshPhoneNumberRule::isValid('+8801712398765'));
        $this->assertEquals('01712398765', BangladeshPhoneNumberRule::toLocalFormat('+8801712398765'));
        $this->assertEquals('+8801712398765', BangladeshPhoneNumberRule::toE164Format('+8801712398765'));

        $this->assertTrue(BangladeshPhoneNumberRule::isValid('8801812398765'));
        $this->assertEquals('01812398765', BangladeshPhoneNumberRule::toLocalFormat('8801812398765'));
    }

    public function test_bangladesh_phone_number_rule_rejects_dummy_and_repeating_numbers(): void
    {
        $dummyNumbers = [
            '01711111111', // all identical subscriber digits
            '01800000000', // repeating zeroes
            '01999999999', // repeating nines
            '01700000000', // known dummy
            '01712345678', // sequential test number
            '01887654321', // descending test number
            '01234567890', // invalid operator prefix (012)
            '01112345678', // invalid operator prefix (011)
            '+14155552671', // international non-BD
            '017123', // too short
            '12345678901', // not starting with 01
        ];

        foreach ($dummyNumbers as $dummy) {
            $this->assertFalse(BangladeshPhoneNumberRule::isValid($dummy), "Failed asserting invalid for {$dummy}");
        }
    }

    public function test_phone_number_rule_integrates_with_laravel_validator(): void
    {
        $rule = new BangladeshPhoneNumberRule;

        // Valid phone validation passes
        $validValidator = Validator::make(
            ['phone' => '01712398765'],
            ['phone' => ['required', $rule]]
        );
        $this->assertTrue($validValidator->passes());

        // Dummy phone validation fails
        $dummyValidator = Validator::make(
            ['phone' => '01711111111'],
            ['phone' => ['required', $rule]]
        );
        $this->assertTrue($dummyValidator->fails());
        $this->assertStringContainsString('dummy test phone number', $dummyValidator->errors()->first('phone'));

        // Invalid format phone fails
        $formatValidator = Validator::make(
            ['phone' => '01299999999'],
            ['phone' => ['required', $rule]]
        );
        $this->assertTrue($formatValidator->fails());
        $this->assertStringContainsString('valid Bangladesh mobile number', $formatValidator->errors()->first('phone'));
    }

    public function test_text_entropy_service_computes_shannon_entropy(): void
    {
        $service = new TextEntropyService;

        $this->assertEquals(0.0, $service->calculateEntropy(''));
        $this->assertEquals(0.0, $service->calculateEntropy('aaaaaaa'));

        // Higher variety should yield higher entropy
        $lowEntropy = $service->calculateEntropy('abababab');
        $highEntropy = $service->calculateEntropy('abcdefgh');

        $this->assertGreaterThan($lowEntropy, $highEntropy);
    }

    public function test_text_entropy_service_identifies_natural_names_and_addresses(): void
    {
        $service = new TextEntropyService;

        $naturalTexts = [
            'Shakil Ahmed',
            'House 42, Road 11, Banani, Dhaka',
            'Dhanmondi 32, Flat 4B, Dhaka-1209',
            'মিরপুর ডিওএইচএস, রোড ৪, বাসা ১২০, ঢাকা',
            'চট্টগ্রাম জিইসি মোড়, নাসিরাবাদ',
        ];

        foreach ($naturalTexts as $text) {
            $eval = $service->evaluate($text);
            $this->assertFalse($eval['is_gibberish'], "Text incorrectly flagged as gibberish: '{$text}' (Score: {$eval['gibberish_score']})");
            $this->assertLessThan(40, $eval['gibberish_score']);
        }
    }

    public function test_text_entropy_service_flags_keyboard_mash_and_dummy_text(): void
    {
        $service = new TextEntropyService;

        $gibberishTexts = [
            'asdfghjkl',
            'qwertzuiop',
            'test',
            'no address',
            'bcdfghjklmn', // consonant cluster with 0 vowels
            'xxxxxxxxxx', // repetitive chars
        ];

        foreach ($gibberishTexts as $text) {
            $eval = $service->evaluate($text);
            $this->assertTrue($eval['is_gibberish'], "Failed to flag gibberish for: '{$text}' (Score: {$eval['gibberish_score']})");
            $this->assertGreaterThanOrEqual(50, $eval['gibberish_score']);
        }
    }

    public function test_order_velocity_action_allows_initial_order(): void
    {
        $action = new CheckOrderVelocityAction;

        $result = $action(
            phone: '01712398765',
            ipAddress: '103.145.12.4',
            deviceFingerprint: 'dev_fingerprint_001',
            paymentMethod: 'cash_on_delivery'
        );

        $this->assertTrue($result->isAllowed);
        $this->assertEquals(0, $result->activeCodOrdersCount);
        $this->assertEquals(0, $result->dailyOrdersFromIpCount);
        $this->assertEquals(0, $result->dailyOrdersFromDeviceCount);
        $this->assertEmpty($result->violations);
    }

    public function test_order_velocity_action_blocks_duplicate_active_cod_order_for_same_phone(): void
    {
        // Place initial COD order in pending status
        Order::create([
            'order_number' => 'ORD-TEST-001',
            'customer_name' => 'Tanvir Hossain',
            'customer_phone' => '01712398765',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_address' => 'House 12, Road 5, Dhanmondi',
            'payment_method' => 'cash_on_delivery',
            'status' => Order::STATUS_PENDING,
        ]);

        $action = new CheckOrderVelocityAction;

        // Second COD order attempt with matching normalized phone (+8801712398765)
        $codResult = $action(
            phone: '+8801712398765',
            ipAddress: '103.145.12.4',
            deviceFingerprint: 'dev_fingerprint_001',
            paymentMethod: 'cash_on_delivery'
        );

        $this->assertFalse($codResult->isAllowed);
        $this->assertEquals(1, $codResult->activeCodOrdersCount);
        $this->assertContains('active_cod_limit_exceeded', $codResult->violations);
        $this->assertStringContainsString('active unfulfilled Cash on Delivery', (string) $codResult->primaryReason);

        // However, placing an order using a digital payment method (e.g., bKash) is allowed!
        $bkashResult = $action(
            phone: '01712398765',
            ipAddress: '103.145.12.4',
            deviceFingerprint: 'dev_fingerprint_001',
            paymentMethod: 'bkash'
        );

        $this->assertTrue($bkashResult->isAllowed);
    }

    public function test_order_velocity_action_allows_new_cod_order_when_previous_order_is_terminal(): void
    {
        // First order was already delivered
        Order::create([
            'order_number' => 'ORD-TEST-DELIVERED',
            'customer_name' => 'Tanvir Hossain',
            'customer_phone' => '01712398765',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_address' => 'House 12, Road 5, Dhanmondi',
            'payment_method' => 'cash_on_delivery',
            'status' => Order::STATUS_DELIVERED,
        ]);

        $action = new CheckOrderVelocityAction;

        $result = $action(
            phone: '01712398765',
            ipAddress: '103.145.12.4',
            paymentMethod: 'cash_on_delivery'
        );

        $this->assertTrue($result->isAllowed);
        $this->assertEquals(0, $result->activeCodOrdersCount);
    }

    public function test_order_velocity_action_throttles_burst_orders_by_ip_address_and_device(): void
    {
        $ip = '103.200.45.12';
        $device = 'fp_browser_hash_999';

        // Create 2 existing orders from the same IP and device within last 24h
        for ($i = 1; $i <= 2; $i++) {
            Order::create([
                'order_number' => "ORD-BURST-00{$i}",
                'customer_name' => "Customer {$i}",
                'customer_phone' => "0181234567{$i}",
                'ip_address' => $ip,
                'device_fingerprint' => $device,
                'division_id' => $this->division->id,
                'district_id' => $this->district->id,
                'delivery_address' => 'Road 1, Dhaka',
                'payment_method' => 'bkash',
                'status' => Order::STATUS_CONFIRMED,
                'created_at' => Carbon::now()->subHours(2),
            ]);
        }

        $action = new CheckOrderVelocityAction;

        // Third order from same IP (different phone)
        $result = $action(
            phone: '01998765432',
            ipAddress: $ip,
            deviceFingerprint: $device,
            paymentMethod: 'bkash',
            maxDailyOrdersPerIp: 2,
            maxDailyOrdersPerDevice: 2
        );

        $this->assertFalse($result->isAllowed);
        $this->assertEquals(2, $result->dailyOrdersFromIpCount);
        $this->assertEquals(2, $result->dailyOrdersFromDeviceCount);
        $this->assertContains('ip_velocity_exceeded', $result->violations);
        $this->assertContains('device_velocity_exceeded', $result->violations);
    }
}
