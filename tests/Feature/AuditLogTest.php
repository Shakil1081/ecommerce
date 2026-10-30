<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'audit_officer@example.com',
        ]);

        $division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA',
        ]);

        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-D',
            'zone_type' => 'inside_dhaka',
        ]);

        $category = Category::create([
            'name' => 'Monitors',
            'slug' => 'monitors',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Dell',
            'slug' => 'dell',
            'origin_country' => 'USA',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Dell Ultrasharp 27 Inch 4K',
            'slug' => 'dell-ultrasharp-27-inch-4k',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '65000.00',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-AUDIT-01',
            'customer_name' => 'Kamal Hossain',
            'customer_phone' => '01700000000',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Banani, Dhaka',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'subtotal_bdt' => '65000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '60.0000',
            'total_bdt' => '65060.0000',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);
    }

    public function test_log_audit_trail_action_records_price_update_with_diff(): void
    {
        $action = app(LogAuditTrailAction::class);

        $oldPrice = ['base_price_bdt' => '65000.00'];
        $newPrice = ['base_price_bdt' => '68000.00'];

        $log = $action(
            event: AuditLog::EVENT_PRICE_UPDATED,
            description: "Base price for product {$this->product->name} updated from 65,000 to 68,000 BDT.",
            auditable: $this->product,
            oldValues: $oldPrice,
            newValues: $newPrice,
            options: [
                'user' => $this->admin,
                'ip_address' => '103.205.132.55',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ]
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertNotEmpty($log->ulid);
        $this->assertSame(AuditLog::EVENT_PRICE_UPDATED, $log->event);
        $this->assertSame(Product::class, $log->auditable_type);
        $this->assertSame((string) $this->product->id, $log->auditable_id);
        $this->assertSame('103.205.132.55', $log->ip_address);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Super Administrator', $log->user_name);
        $this->assertSame('65000.00', $log->old_values['base_price_bdt']);
        $this->assertSame('68000.00', $log->new_values['base_price_bdt']);

        // Verify morph relation
        $this->assertTrue($log->auditable->is($this->product));
    }

    public function test_log_audit_trail_action_records_stock_adjustment(): void
    {
        $action = app(LogAuditTrailAction::class);

        $log = $action(
            event: AuditLog::EVENT_STOCK_ADJUSTED,
            description: 'Physical inventory adjusted by +50 units following GRN inward intake.',
            auditable: $this->product,
            oldValues: ['physical_quantity' => 20],
            newValues: ['physical_quantity' => 70],
            options: ['user' => $this->admin]
        );

        $this->assertSame(AuditLog::EVENT_STOCK_ADJUSTED, $log->event);
        $this->assertSame(20, $log->old_values['physical_quantity']);
        $this->assertSame(70, $log->new_values['physical_quantity']);
    }

    public function test_log_audit_trail_action_records_order_status_change_with_auth_context(): void
    {
        $this->actingAs($this->admin);

        $action = app(LogAuditTrailAction::class);

        $log = $action(
            event: AuditLog::EVENT_ORDER_STATUS_CHANGED,
            description: 'Order status manually transitioned from confirmed to processing.',
            auditable: $this->order,
            oldValues: ['status' => 'confirmed'],
            newValues: ['status' => 'processing']
        );

        $this->assertSame(AuditLog::EVENT_ORDER_STATUS_CHANGED, $log->event);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('audit_officer@example.com', $log->user_email);
        $this->assertSame(Order::class, $log->auditable_type);
        $this->assertSame((string) $this->order->id, $log->auditable_id);
    }
}
