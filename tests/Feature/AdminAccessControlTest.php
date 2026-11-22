<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedAdminEndpoints(): array
    {
        return [
            'orders index' => ['GET', '/admin/orders'],
            'payment gateway settings' => ['GET', '/admin/settings/payment-gateways'],
            'save payment gateway credentials' => ['POST', '/admin/settings/payment-gateways'],
            'courier settings' => ['GET', '/admin/settings/couriers'],
            'roles index' => ['GET', '/admin/roles'],
            'create staff' => ['POST', '/admin/staff'],
            'fraud directory' => ['GET', '/admin/fraud-directory'],
            'mail settings' => ['GET', '/admin/communication/mail-settings'],
            'ai settings' => ['GET', '/admin/ai-settings'],
            'trading pnl' => ['GET', '/admin/analytics/trading-pnl'],
            'audit logs' => ['GET', '/admin/audit-logs'],
            'tax dashboard' => ['GET', '/admin/tax-compliance'],
        ];
    }

    /**
     * @dataProvider protectedAdminEndpoints
     */
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $response->assertRedirect('/login');
    }

    /**
     * @dataProvider protectedAdminEndpoints
     */
    public function test_storefront_customers_without_a_staff_role_are_forbidden(string $method, string $uri): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->call($method, $uri);

        $response->assertForbidden();
    }

    public function test_staff_can_only_reach_modules_granted_by_their_role(): void
    {
        $warehouse = User::factory()->create();
        $warehouse->assignRole('Warehouse Staff');

        $this->actingAs($warehouse)->get('/admin/orders')->assertOk();

        $this->actingAs($warehouse)->get('/admin/settings/payment-gateways')->assertForbidden();
        $this->actingAs($warehouse)->post('/admin/settings/payment-gateways', [])->assertForbidden();
        $this->actingAs($warehouse)->get('/admin/roles')->assertForbidden();
        $this->actingAs($warehouse)->get('/admin/analytics/trading-pnl')->assertForbidden();
        $this->actingAs($warehouse)->get('/admin/tax-compliance')->assertForbidden();
    }

    public function test_view_permission_does_not_grant_write_access(): void
    {
        $this->actingAsStaffWith(['settings.view']);

        $this->get('/admin/settings/payment-gateways')->assertOk();
        $this->post('/admin/settings/payment-gateways', [])->assertForbidden();
        $this->post('/admin/communication/mail-settings', [])->assertForbidden();
    }

    public function test_dashboard_lands_staff_on_first_accessible_module(): void
    {
        $support = User::factory()->create();
        $support->assignRole('Customer Support');

        $this->actingAs($support)->get('/admin')->assertRedirect(route('admin.orders.index'));
    }

    public function test_super_admin_lands_on_trading_pnl(): void
    {
        $this->actingAsSuperAdmin()->get('/admin')->assertRedirect(route('admin.analytics.pnl'));
    }
}
