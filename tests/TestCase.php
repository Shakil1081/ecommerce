<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as a back-office Super Admin (bypasses permission checks via Gate::before).
     *
     * Admin routes require `auth` + a staff role + module permissions, so admin feature tests
     * must use this instead of a bare actingAs() with a role-less user.
     */
    protected function actingAsSuperAdmin(?User $user = null): static
    {
        $user ??= User::factory()->create();

        $role = Role::findOrCreate('Super Admin', 'web');
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->actingAs($user);
    }

    /**
     * A valid signed timing token rendered `$secondsAgo` in the past, so storefront order
     * submissions pass the `ensure.human` bot timing guard like a real customer would.
     */
    protected function humanTimingToken(float $secondsAgo = 10.0): string
    {
        return app(HoneypotTimingGuardService::class)->generateTimingToken(microtime(true) - $secondsAgo);
    }

    /**
     * Authenticate as a staff user holding only the given permissions (for RBAC boundary tests).
     *
     * @param  array<int, string>  $permissions
     */
    protected function actingAsStaffWith(array $permissions, ?User $user = null): static
    {
        $user ??= User::factory()->create();

        $role = Role::findOrCreate('Test Staff '.uniqid(), 'web');
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->actingAs($user);
    }
}
