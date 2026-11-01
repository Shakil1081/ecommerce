<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin@ecommerce.test|127.0.0.1');

        $this->admin = User::factory()->create([
            'name' => 'Admin Tester',
            'email' => 'admin@ecommerce.test',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->has('botSecurity')
            ->has('timingToken')
            ->has('honeypotField')
        );
    }

    public function test_authenticated_user_visiting_login_redirects_to_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/login');

        $response->assertRedirect('/admin/dashboard');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@ecommerce.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/admin/dashboard');

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.success',
            'user_email' => 'admin@ecommerce.test',
        ]);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@ecommerce.test',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.failed',
        ]);
    }

    public function test_login_rejects_automated_honeypot_bot_submission(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@ecommerce.test',
            'password' => 'password',
            'user_company_fax' => 'AutomatedSpamBotValue', // Honeypot trap triggered
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.bot_detected',
        ]);
    }

    public function test_brute_force_throttling_locks_out_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin@ecommerce.test',
                'password' => 'wrong-pass',
            ]);
        }

        // 6th attempt should be blocked with rate limiting
        $response = $this->post('/login', [
            'email' => 'admin@ecommerce.test',
            'password' => 'wrong-pass',
        ]);

        $response->assertSessionHasErrors('email');

        // Verify audit log entry for throttled attempt
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login.throttled',
        ]);
    }

    public function test_authenticated_user_can_logout_via_post_logout(): void
    {
        $response = $this->actingAs($this->admin)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHas('info');

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.logout',
            'user_email' => 'admin@ecommerce.test',
        ]);
    }

    public function test_authenticated_user_can_logout_via_admin_logout_alias(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
