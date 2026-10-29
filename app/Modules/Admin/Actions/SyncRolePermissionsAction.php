<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissionsAction
{
    /**
     * Synchronize permissions for a specific role and clear cached permissions.
     *
     * @param  array<int, string>  $permissionNames
     */
    public function __invoke(Role $role, array $permissionNames): Role
    {
        $role->syncPermissions($permissionNames);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $role;
    }
}

