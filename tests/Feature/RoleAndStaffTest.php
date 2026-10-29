<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAndStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seeder_initializes_roles_and_permissions(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
        $this->assertDatabaseHas('roles', ['name' => 'Operations Manager']);
        $this->assertDatabaseHas('roles', ['name' => 'Procurement Officer']);
        $this->assertDatabaseHas('roles', ['name' => 'Accounts & VAT Officer']);
        $this->assertDatabaseHas('roles', ['name' => 'Warehouse Staff']);
        $this->assertDatabaseHas('roles', ['name' => 'Customer Support']);

        $this->assertDatabaseHas('permissions', ['name' => 'catalog.create']);
        $this->assertDatabaseHas('permissions', ['name' => 'tax.mushak_6_3']);
        $this->assertDatabaseHas('permissions', ['name' => 'logistics.book']);
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'admin@tradinghub.com.bd',
        ]);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin);

        // Super Admin has all abilities via Gate::before
        $this->assertTrue(Gate::allows('catalog.delete'));
        $this->assertTrue(Gate::allows('tax.mushak_9_1'));
        $this->assertTrue(Gate::allows('arbitrary.nonexistent.permission'));
    }

    public function test_restricted_user_obeys_assigned_role_permissions(): void
    {
        $warehouseWorker = User::factory()->create([
            'email' => 'warehouse@tradinghub.com.bd',
        ]);
        $warehouseWorker->assignRole('Warehouse Staff');

        $this->actingAs($warehouseWorker);

        // Warehouse staff can adjust inventory
        $this->assertTrue(Gate::allows('inventory.adjust'));

        // Warehouse staff cannot issue VAT Mushak 6.3 invoices
        $this->assertFalse(Gate::allows('tax.mushak_6_3'));
        $this->assertFalse(Gate::allows('catalog.delete'));
    }

    public function test_admin_can_access_roles_index_and_create_custom_role(): void
    {
        $response = $this->get(route('admin.roles.index'));
        $response->assertOk();

        // Create a custom role
        $createResponse = $this->post(route('admin.roles.store'), [
            'name' => 'Custom Dispatcher',
            'permissions' => ['logistics.view', 'logistics.book'],
        ]);

        $createResponse->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Custom Dispatcher']);

        $customRole = Role::findByName('Custom Dispatcher');
        $this->assertTrue($customRole->hasPermissionTo('logistics.book'));
        $this->assertFalse($customRole->hasPermissionTo('tax.mushak_6_3'));
    }

    public function test_cannot_delete_super_admin_role(): void
    {
        $superAdminRole = Role::findByName('Super Admin');

        $response = $this->delete(route('admin.roles.destroy', $superAdminRole));
        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
    }

    public function test_can_manage_staff_users(): void
    {
        $response = $this->get(route('admin.staff.index'));
        $response->assertOk();

        // Create staff user
        $storeResponse = $this->post(route('admin.staff.store'), [
            'name' => 'Sourcing Specialist',
            'email' => 'sourcing@tradinghub.com.bd',
            'password' => 'Password123#Secure',
            'roles' => ['Procurement Officer'],
        ]);

        $storeResponse->assertRedirect(route('admin.staff.index'));
        $this->assertDatabaseHas('users', ['email' => 'sourcing@tradinghub.com.bd']);

        $user = User::where('email', 'sourcing@tradinghub.com.bd')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Procurement Officer'));
        $this->assertTrue(Hash::check('Password123#Secure', $user->password));
    }
}
