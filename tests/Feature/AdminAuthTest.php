<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@ecommerce.test',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_logout_via_post_logout(): void
    {
        $response = $this->actingAs($this->admin)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHas('info');
    }

    public function test_authenticated_user_can_logout_via_admin_logout_alias(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
