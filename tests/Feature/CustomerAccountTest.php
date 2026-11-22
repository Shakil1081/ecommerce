<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\CustomerAddress;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsStorefrontFixtures;
use Tests\TestCase;

final class CustomerAccountTest extends TestCase
{
    use BuildsStorefrontFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildStorefrontFixtures();
    }

    public function test_customer_can_register_and_is_logged_in_without_any_role(): void
    {
        $this->post('/account/register', [
            'name' => 'Tahmid Hasan',
            'email' => 'Tahmid@Example.com',
            'phone' => '+880 1812-345678',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'user_company_fax' => '',
            '_form_timing_token' => $this->humanTimingToken(),
        ])->assertRedirect('/account');

        $user = User::query()->where('email', 'tahmid@example.com')->firstOrFail();
        $this->assertSame('01812345678', $user->phone);
        $this->assertFalse($user->isStaff());
        $this->assertAuthenticatedAs($user);

        // Customers cannot reach the back office
        $this->get('/admin')->assertForbidden();
    }

    public function test_registration_rejects_invalid_phone_and_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/account/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'phone' => '12345',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            '_form_timing_token' => $this->humanTimingToken(),
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_can_log_in_with_email_or_mobile_number(): void
    {
        User::factory()->create([
            'email' => 'mitu@example.com',
            'phone' => '01911223344',
            'password' => Hash::make('secret-pass-1'),
        ]);

        $this->post('/account/login', ['login' => '01911-223344', 'password' => 'secret-pass-1'])
            ->assertRedirect('/account');
        $this->post('/account/logout')->assertRedirect('/');

        $this->post('/account/login', ['login' => 'MITU@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect('/account');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'mitu@example.com', 'password' => Hash::make('secret-pass-1')]);

        $this->post('/account/login', ['login' => 'mitu@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_customer_login_not_staff_login(): void
    {
        $this->get('/account/orders')->assertRedirect('/account/login');
        $this->get('/admin/products')->assertRedirect('/login');
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post('/account/forgot-password', [
            'email' => 'reset@example.com',
            '_form_timing_token' => $this->humanTimingToken(),
        ])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->get('/account/reset-password/'.$token.'?email=reset@example.com')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Storefront/Account/Auth/ResetPassword'));

        $this->post('/account/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/account/login');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_forgot_password_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->post('/account/forgot-password', [
            'email' => 'nobody@example.com',
            '_form_timing_token' => $this->humanTimingToken(),
        ])->assertSessionHas('success')->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_customer_sees_only_their_own_orders(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->createOrder(['user_id' => $customer->id]);
        $theirs = $this->createOrder(['user_id' => $other->id]);

        $this->actingAs($customer)->get('/account/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Account/Orders')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $mine->order_number));

        $this->actingAs($customer)->get('/account/orders/'.$mine->order_number)->assertOk();
        $this->actingAs($customer)->get('/account/orders/'.$theirs->order_number)->assertNotFound();
    }

    public function test_customer_can_manage_addresses_and_checkout_prefills_default(): void
    {
        $customer = User::factory()->create(['phone' => '01700000001']);

        $this->actingAs($customer)->post('/account/addresses', [
            'label' => 'Home',
            'recipient_name' => 'Nabila',
            'phone' => '01711112222',
            'district_id' => $this->district->id,
            'address_line' => 'Flat 4B, Road 11, Banani',
        ])->assertSessionHasNoErrors();

        $address = CustomerAddress::query()->firstOrFail();
        $this->assertTrue($address->is_default, 'First address becomes the default');

        $this->actingAs($customer)->get('/checkout')
            ->assertInertia(fn (Assert $page) => $page
                ->where('customerDefaults.customer_name', 'Nabila')
                ->where('customerDefaults.customer_phone', '01711112222')
                ->where('customerDefaults.delivery_address', 'Flat 4B, Road 11, Banani')
                ->where('customerDefaults.delivery_zone', 'inside_dhaka'));

        // Another customer cannot touch it
        $intruder = User::factory()->create();
        $this->actingAs($intruder)->delete("/account/addresses/{$address->id}")->assertNotFound();

        $this->actingAs($customer)->delete("/account/addresses/{$address->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, CustomerAddress::query()->count());
    }

    public function test_customer_can_update_profile_and_password(): void
    {
        $customer = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($customer)->put('/account/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '01555666777',
        ])->assertSessionHasNoErrors();

        $this->assertSame('01555666777', $customer->fresh()->phone);

        $this->actingAs($customer)->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($customer)->put('/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-1', $customer->fresh()->password));
    }

    public function test_customer_using_staff_login_lands_in_their_account(): void
    {
        User::factory()->create(['email' => 'shopper@example.com', 'password' => Hash::make('secret-pass-1')]);

        $this->post('/login', ['email' => 'shopper@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect('/account');
    }
}
