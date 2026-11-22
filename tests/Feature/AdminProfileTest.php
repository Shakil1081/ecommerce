<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_staff_member_can_open_their_profile(): void
    {
        $staff = User::factory()->create(['name' => 'Rina Akter']);

        // A role with no module permissions at all still reaches My Profile
        $this->actingAsStaffWith([], $staff)
            ->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profile/Edit')
                ->where('profile.name', 'Rina Akter'));
    }

    public function test_staff_can_update_name_and_email(): void
    {
        $staff = User::factory()->create();

        $this->actingAsSuperAdmin($staff)
            ->put('/admin/profile', ['name' => 'Store Owner', 'email' => 'Owner@WebSpreed.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('owner@webspreed.com', $staff->fresh()->email);
        $this->assertSame('Store Owner', $staff->fresh()->name);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAsSuperAdmin()
            ->put('/admin/profile', ['name' => 'Someone', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $staff = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAsSuperAdmin($staff)
            ->put('/admin/profile/password', [
                'current_password' => 'wrong',
                'password' => 'new-strong-pass',
                'password_confirmation' => 'new-strong-pass',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAsSuperAdmin($staff)
            ->put('/admin/profile/password', [
                'current_password' => 'password',
                'password' => 'new-strong-pass',
                'password_confirmation' => 'new-strong-pass',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-strong-pass', $staff->fresh()->password));
    }

    public function test_customers_cannot_use_the_admin_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/profile')
            ->assertForbidden();
    }
}
