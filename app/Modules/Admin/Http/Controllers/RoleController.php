<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\SyncRolePermissionsAction;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of enterprise roles.
     */
    public function index(): Response
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_protected' => $role->name === 'Super Admin',
            ]);

        return Inertia::render('Admin/Roles/RoleIndex', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Roles/RoleForm', [
            'role' => null,
            'permissionGroups' => $this->getFormattedPermissionGroups(),
            'assignedPermissions' => [],
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request, SyncRolePermissionsAction $syncAction): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $syncAction($role, $validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' created successfully.");
    }

    /**
     * Show the form for editing an existing role.
     */
    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/RoleForm', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => $role->name === 'Super Admin',
            ],
            'permissionGroups' => $this->getFormattedPermissionGroups(),
            'assignedPermissions' => $role->permissions()->pluck('name')->toArray(),
        ]);
    }

    /**
     * Update an existing role and its permissions.
     */
    public function update(Request $request, Role $role, SyncRolePermissionsAction $syncAction): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name !== 'Super Admin') {
            $role->update(['name' => $validated['name']]);
        }

        $syncAction($role, $validated['permissions'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' updated successfully.");
    }

    /**
     * Delete an existing role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'The Super Admin role cannot be deleted.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' deleted successfully.");
    }

    /**
     * Format permission groups for UI checkboxes.
     *
     * @return array<int, array{name: string, permissions: array<int, array{name: string, label: string}>}>
     */
    private function getFormattedPermissionGroups(): array
    {
        $groups = [];
        foreach (RolesAndPermissionsSeeder::PERMISSION_GROUPS as $groupName => $perms) {
            $permissionList = [];
            foreach ($perms as $permName => $label) {
                $permissionList[] = [
                    'name' => $permName,
                    'label' => $label,
                ];
            }
            $groups[] = [
                'name' => $groupName,
                'permissions' => $permissionList,
            ];
        }

        return $groups;
    }
}

