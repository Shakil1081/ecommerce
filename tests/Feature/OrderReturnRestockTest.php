<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Inventory\Actions\CommitStockReservationAction;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReturnRestockTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_returned_order_atomically_restocks_inventory(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => 'DHA']);
        $district = BangladeshDistrict::create(['division_id' => $division->id, 'name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => 'DHA']);

        $warehouse = Warehouse::create([
            'name' => 'Central Hub Dhaka',
            'code' => 'HUB-DHA-01',
            'address' => 'Tejgaon I/A, Dhaka',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Earbuds',
            'slug' => 'wireless-earbuds',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'EARBUDS-BLK',
            'attributes' => ['color' => 'Black'],
            'purchase_price_bdt' => 1000,
            'selling_price_bdt' => 1800,
            'is_active' => true,
        ]);

        // Initial stock: 10 units
        $stock = InventoryStock::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'physical_quantity' => 10,
            'reserved_quantity' => 0,
            'bin_location' => 'A-01',
        ]);

        // Create order for 2 units
        $order = Order::create([
            'order_number' => 'ORD-RETURN-TEST',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Test Customer',
            'customer_phone' => '01712345678',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Mirpur-10, Dhaka',
            'subtotal_bdt' => 3600,
            'discount_amount_bdt' => 0,
            'shipping_fee_bdt' => 70,
            'total_bdt' => 3670,
            'advance_paid_bdt' => 0,
            'payable_cod_amount_bdt' => 3670,
            'fraud_risk_score' => 15,
            'fraud_risk_level' => 'low',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'unit_price_bdt' => 1800,
            'quantity' => 2,
            'subtotal_bdt' => 3600,
            'unit_cogs_bdt' => 1000,
            'total_cogs_bdt' => 2000,
        ]);

        // Reserve and commit stock as if handed to courier
        app(ReserveStockForOrderAction::class)($order);
        app(CommitStockReservationAction::class)($order);
        $order->update(['status' => Order::STATUS_HANDED_TO_COURIER]);

        $stock->refresh();
        $this->assertEquals(8, $stock->physical_quantity); // 10 - 2 = 8

        // Admin receives returned package from courier
        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/transition", [
                'target_status' => Order::STATUS_RETURNED,
                'reason' => 'Customer refused parcel - package received back from Steadfast',
            ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(Order::STATUS_RETURNED, $order->status);

        // Verify stock is restored back to 10
        $stock->refresh();
        $this->assertEquals(10, $stock->physical_quantity);
    }
}
