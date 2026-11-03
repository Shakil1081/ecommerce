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

class ScanCourierReturnsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private InventoryStock $stock;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => 'DHA']);
        $this->district = BangladeshDistrict::create(['division_id' => $this->division->id, 'name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => 'DHA']);

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub Dhaka',
            'code' => 'HUB-DHA-01',
            'address' => 'Tejgaon I/A, Dhaka',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Gadgets',
            'slug' => 'gadgets',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bluetooth Speaker',
            'slug' => 'bluetooth-speaker',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SPK-BT-RED',
            'attributes' => ['color' => 'Red'],
            'purchase_price_bdt' => 1200,
            'selling_price_bdt' => 2200,
            'is_active' => true,
        ]);

        $this->stock = InventoryStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'physical_quantity' => 15,
            'reserved_quantity' => 0,
            'bin_location' => 'B-04',
        ]);
    }

    private function createDispatchedOrder(string $orderNumber, string $trackingCode): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'courier_code' => 'steadfast',
            'courier_tracking_code' => $trackingCode,
            'courier_consignment_id' => 'STDF-'.rand(100000, 999999),
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'John Doe',
            'customer_phone' => '01811223344',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Dhanmondi 27, Dhaka',
            'subtotal_bdt' => 4400,
            'discount_amount_bdt' => 0,
            'shipping_fee_bdt' => 70,
            'total_bdt' => 4470,
            'advance_paid_bdt' => 0,
            'payable_cod_amount_bdt' => 4470,
            'fraud_risk_score' => 10,
            'fraud_risk_level' => 'low',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Bluetooth Speaker',
            'sku' => $this->variant->sku,
            'unit_price_bdt' => 2200,
            'quantity' => 2,
            'subtotal_bdt' => 4400,
            'unit_cogs_bdt' => 1200,
            'total_cogs_bdt' => 2400,
        ]);

        // Deduct physical inventory (15 -> 13)
        app(ReserveStockForOrderAction::class)($order);
        app(CommitStockReservationAction::class)($order);
        $order->update(['status' => Order::STATUS_HANDED_TO_COURIER]);

        return $order;
    }

    public function test_scan_returns_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders/scan-returns');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/ScanReturnsConsole')
            ->has('stats')
            ->has('recentReturns')
        );
    }

    public function test_scan_barcode_by_courier_tracking_code_receives_return_and_restocks(): void
    {
        $order = $this->createDispatchedOrder('ORD-SCAN-01', 'STDF-TRACK-9988');

        $this->stock->refresh();
        $this->assertEquals(13, $this->stock->physical_quantity);

        $response = $this->actingAs($this->admin)->postJson('/admin/orders/scan-returns/process', [
            'barcode' => 'STDF-TRACK-9988',
            'notes' => 'Customer unreachable after 3 attempts',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'order' => [
                'order_number' => 'ORD-SCAN-01',
                'status' => Order::STATUS_RETURNED,
                'courier_code' => 'STEADFAST',
                'courier_tracking_code' => 'STDF-TRACK-9988',
                'items_count' => 2,
            ],
        ]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_RETURNED, $order->status);

        // Stock must be restored: 13 + 2 = 15
        $this->stock->refresh();
        $this->assertEquals(15, $this->stock->physical_quantity);
    }

    public function test_scan_barcode_by_order_number_receives_return(): void
    {
        $order = $this->createDispatchedOrder('ORD-SCAN-02', 'STDF-TRACK-7766');

        $response = $this->actingAs($this->admin)->postJson('/admin/orders/scan-returns/process', [
            'barcode' => 'ORD-SCAN-02',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'order' => [
                'order_number' => 'ORD-SCAN-02',
                'status' => Order::STATUS_RETURNED,
            ],
        ]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_RETURNED, $order->status);
    }

    public function test_duplicate_scan_is_prevented_and_does_not_double_restock(): void
    {
        $order = $this->createDispatchedOrder('ORD-SCAN-03', 'STDF-TRACK-5544');

        // First scan: successful return
        $firstScan = $this->actingAs($this->admin)->postJson('/admin/orders/scan-returns/process', [
            'barcode' => 'STDF-TRACK-5544',
        ]);
        $firstScan->assertOk();
        $firstScan->assertJson(['success' => true]);

        $this->stock->refresh();
        $this->assertEquals(15, $this->stock->physical_quantity);

        // Second scan: duplicate alert
        $secondScan = $this->actingAs($this->admin)->postJson('/admin/orders/scan-returns/process', [
            'barcode' => 'STDF-TRACK-5544',
        ]);

        $secondScan->assertOk();
        $secondScan->assertJson([
            'success' => false,
            'already_returned' => true,
            'order' => [
                'order_number' => 'ORD-SCAN-03',
            ],
        ]);

        // Stock must NOT be double-incremented
        $this->stock->refresh();
        $this->assertEquals(15, $this->stock->physical_quantity);
    }

    public function test_scan_invalid_barcode_returns_not_found(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/orders/scan-returns/process', [
            'barcode' => 'INVALID-BARCODE-99999',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('No order found', $response->json('message'));
    }
}
