<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Grouped system permissions by domain module.
     *
     * @var array<string, array<string, string>>
     */
    public const PERMISSION_GROUPS = [
        'Catalog Management' => [
            'catalog.view' => 'View products, categories, brands and matrix variants',
            'catalog.create' => 'Create new products, brands and categories',
            'catalog.edit' => 'Modify product information, pricing and media',
            'catalog.delete' => 'Delete or archive catalog items',
        ],
        'Procurement & Sourcing' => [
            'procurement.view' => 'View purchase orders, commercial invoices and suppliers',
            'procurement.create' => 'Create local POs and global import orders',
            'procurement.approve' => 'Approve purchase orders and settlement exchange rates',
            'procurement.customs' => 'Manage Bill of Entry (BOE), HS codes and import tariffs',
        ],
        'Inventory & Warehouses' => [
            'inventory.view' => 'View multi-warehouse stock levels and FIFO valuation',
            'inventory.adjust' => 'Perform inventory count adjustments and discrepancy write-offs',
            'inventory.transfer' => 'Execute inter-warehouse stock transfers with Mushak 6.5',
        ],
        'Orders & Anti-Fraud' => [
            'orders.view' => 'View customer orders and shipment timeline',
            'orders.edit' => 'Update order status, delivery address and notes',
            'orders.cancel' => 'Cancel orders and trigger stock replenishment',
            'orders.fraud_review' => 'Inspect high-risk order queue, OTP logs and manage blacklist',
        ],
        'Logistics & Couriers' => [
            'logistics.view' => 'View courier consignments and delivery statuses',
            'logistics.book' => 'Book parcels with Steadfast, Pathao and RedX',
            'logistics.manage_couriers' => 'Configure courier API keys and dynamic shipping zones',
        ],
        'NBR Tax & Compliance' => [
            'tax.view' => 'Inspect statutory purchase and sales VAT registers',
            'tax.mushak_6_3' => 'Issue and lock statutory Mushak 6.3 tax invoices',
            'tax.mushak_6_6' => 'Issue Mushak 6.6 VDS withholding tax certificates',
            'tax.mushak_9_1' => 'Generate and review monthly Mushak 9.1 VAT returns',
        ],
        'Administration & RBAC' => [
            'settings.view' => 'View back-office system settings and storage info',
            'settings.edit' => 'Update SMTP, multi-cloud storage and store preferences',
            'rbac.manage' => 'Create and assign roles, permissions and staff accounts',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create all permissions
        foreach (self::PERMISSION_GROUPS as $groupName => $permissions) {
            foreach ($permissions as $permissionName => $description) {
                Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web']
                );
            }
        }

        // 2. Define enterprise roles and assign permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        // Super Admin gets all permissions by default (and Gate::before bypass)
        $superAdmin->syncPermissions(Permission::all());

        $operationsManager = Role::firstOrCreate(['name' => 'Operations Manager', 'guard_name' => 'web']);
        $operationsManager->syncPermissions([
            'catalog.view', 'catalog.create', 'catalog.edit',
            'inventory.view', 'inventory.transfer',
            'orders.view', 'orders.edit', 'orders.cancel', 'orders.fraud_review',
            'logistics.view', 'logistics.book',
            'settings.view',
        ]);

        $procurementOfficer = Role::firstOrCreate(['name' => 'Procurement Officer', 'guard_name' => 'web']);
        $procurementOfficer->syncPermissions([
            'procurement.view', 'procurement.create', 'procurement.approve', 'procurement.customs',
            'inventory.view',
            'catalog.view',
        ]);

        $taxOfficer = Role::firstOrCreate(['name' => 'Accounts & VAT Officer', 'guard_name' => 'web']);
        $taxOfficer->syncPermissions([
            'tax.view', 'tax.mushak_6_3', 'tax.mushak_6_6', 'tax.mushak_9_1',
            'procurement.view',
            'orders.view',
            'inventory.view',
        ]);

        $warehouseStaff = Role::firstOrCreate(['name' => 'Warehouse Staff', 'guard_name' => 'web']);
        $warehouseStaff->syncPermissions([
            'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'orders.view',
            'logistics.view', 'logistics.book',
        ]);

        $customerSupport = Role::firstOrCreate(['name' => 'Customer Support', 'guard_name' => 'web']);
        $customerSupport->syncPermissions([
            'orders.view', 'orders.edit', 'orders.fraud_review',
            'logistics.view',
            'catalog.view',
        ]);
    }
}
