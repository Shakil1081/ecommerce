<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Communication\Models\SmsOtp;
use App\Modules\Order\Actions\ResolvePostOrderRedirectAction;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsStorefrontFixtures;
use Tests\TestCase;

final class OrderOtpVerificationTest extends TestCase
{
    use BuildsStorefrontFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildStorefrontFixtures();
    }

    public function test_low_risk_order_goes_straight_to_confirmation(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'low']);

        $url = app(ResolvePostOrderRedirectAction::class)($order);

        $this->assertSame(route('storefront.order-confirmed', ['order' => $order->order_number]), $url);
        $this->assertSame(0, SmsOtp::query()->count());
    }

    public function test_medium_risk_order_sends_otp_and_redirects_to_verification(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'medium', 'fraud_risk_score' => 45]);

        $url = app(ResolvePostOrderRedirectAction::class)($order, '127.0.0.1');

        $this->assertSame(route('storefront.order-verify', ['order' => $order->order_number]), $url);
        $this->assertDatabaseHas('sms_otps', [
            'order_id' => $order->id,
            'phone_number' => '01712345678',
            'purpose' => 'order_verification',
        ]);
    }

    public function test_confirmation_page_redirects_unverified_medium_risk_order_to_verification(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'medium']);

        $this->get(route('storefront.order-confirmed', ['order' => $order->order_number]))
            ->assertRedirect(route('storefront.order-verify', ['order' => $order->order_number]));
    }

    public function test_verification_page_masks_the_phone_number(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'high']);

        $this->get(route('storefront.order-verify', ['order' => $order->order_number]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/OrderVerify')
                ->where('orderNumber', $order->order_number)
                ->where('maskedPhone', '017*****678'));
    }

    public function test_correct_code_verifies_the_order(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'medium']);
        $this->issueKnownOtp($order, '4821');

        $this->post(route('storefront.order-verify.submit', ['order' => $order->order_number]), ['code' => '4821'])
            ->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));

        $order->refresh();
        $this->assertNotNull($order->otp_verified_at);
        $this->assertFalse($order->requiresOtpVerification());
        $this->assertTrue(OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('reason', 'Customer confirmed the order with SMS OTP')
            ->exists());

        // Confirmation page is now reachable
        $this->get(route('storefront.order-confirmed', ['order' => $order->order_number]))->assertOk();
    }

    public function test_wrong_code_is_rejected_and_locks_after_three_attempts(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'medium']);
        $this->issueKnownOtp($order, '4821');

        $verifyUrl = route('storefront.order-verify.submit', ['order' => $order->order_number]);

        for ($i = 0; $i < 3; $i++) {
            $this->from(route('storefront.order-verify', ['order' => $order->order_number]))
                ->post($verifyUrl, ['code' => '0000'])
                ->assertSessionHasErrors('code');
        }

        // Even the right code is refused once attempts are used up
        $this->post($verifyUrl, ['code' => '4821'])->assertSessionHasErrors('code');
        $this->assertNull($order->fresh()->otp_verified_at);
    }

    public function test_resend_is_blocked_during_cooldown(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'medium']);
        $this->issueKnownOtp($order, '4821');

        $this->post(route('storefront.order-verify.resend', ['order' => $order->order_number]))
            ->assertSessionHasErrors('code');

        $this->assertSame(1, SmsOtp::query()->where('order_id', $order->id)->count());
    }

    public function test_verified_or_low_risk_orders_skip_the_verification_page(): void
    {
        $order = $this->createOrder(['fraud_risk_level' => 'medium', 'otp_verified_at' => now()]);

        $this->get(route('storefront.order-verify', ['order' => $order->order_number]))
            ->assertRedirect(route('storefront.order-confirmed', ['order' => $order->order_number]));
    }

    private function issueKnownOtp(Order $order, string $code): void
    {
        SmsOtp::create([
            'phone_number' => $order->customer_phone,
            'otp_code_hash' => Hash::make($code),
            'purpose' => 'order_verification',
            'order_id' => $order->id,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }
}
