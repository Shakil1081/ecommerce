<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cart\Actions\AddToCartAction;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Actions\CreateOrderFromCartAction;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Order $order;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $this->warehouse = Warehouse::create([
            'name' => 'Dhaka Hub',
            'code' => 'DHA-HUB-01',
            'is_primary' => true,
            'is_active' => true,
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
            'code' => 'DHA-01',
            'zone_type' => 'inside_dhaka',
        ]);

        $category = Category::create([
            'name' => 'Tablets',
            'slug' => 'tablets',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'country_of_origin' => 'US',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'iPad Air M2',
            'slug' => 'ipad-air-m2',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'USA',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IPAD-AIR-M2-128',
            'barcode_ean' => '195949112233',
            'attributes' => ['color' => 'Space Gray'],
            'purchase_cost_bdt' => '65000.0000',
            'selling_price_bdt' => '78000.0000',
            'is_active' => true,
        ]);

        InventoryStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'physical_quantity' => 20,
            'reserved_quantity' => 0,
        ]);

        $getCart = app(GetActiveCartAction::class);
        $addToCart = app(AddToCartAction::class);
        $createOrder = app(CreateOrderFromCartAction::class);

        $cart = $getCart($this->admin);
        $cart = $addToCart($cart, $this->variant, 2);

        $this->order = $createOrder($cart, [
            'customer_name' => 'Naveed Iqbal',
            'customer_phone' => '01711998877',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_address' => 'Baridhara DOHS, Dhaka',
        ], $this->admin);
    }

    public function test_admin_can_view_orders_index_with_filters_and_counts(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/OrderIndex')
                ->has('orders.data', 1)
                ->has('counts')
                ->where('counts.pending', 1)
                ->where('counts.all', 1)
                ->has('filters')
            );
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders?status=confirmed');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/OrderIndex')
                ->has('orders.data', 0)
                ->where('filters.status', 'confirmed')
            );
    }

    public function test_admin_can_view_order_detail_page(): void
    {
        $response = $this->actingAs($this->admin)->get("/admin/orders/{$this->order->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/OrderDetail')
                ->where('order.id', $this->order->id)
                ->where('order.order_number', $this->order->order_number)
                ->has('order.items', 1)
                ->has('allowedTransitions')
            );
    }

    public function test_admin_can_transition_order_status_via_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->post("/admin/orders/{$this->order->id}/transition", [
            'target_status' => Order::STATUS_CONFIRMED,
            'reason' => 'Customer confirmed delivery address over phone',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_CONFIRMED, $this->order->status);
        $this->assertNotNull($this->order->confirmed_at);

        // Assert status history record
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $this->order->id,
            'from_status' => Order::STATUS_PENDING,
            'to_status' => Order::STATUS_CONFIRMED,
            'reason' => 'Customer confirmed delivery address over phone',
            'changed_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_bulk_confirm_pending_orders(): void
    {
        $getCart = app(GetActiveCartAction::class);
        $addToCart = app(AddToCartAction::class);
        $createOrder = app(CreateOrderFromCartAction::class);

        $cart2 = $getCart($this->admin);
        $cart2 = $addToCart($cart2, $this->variant, 1);
        $order2 = $createOrder($cart2, [
            'customer_name' => 'Sadia Islam',
            'customer_phone' => '01811223344',
            'division_id' => $this->order->division_id,
            'district_id' => $this->order->district_id,
            'delivery_address' => 'Gulshan 2, Dhaka',
        ], $this->admin);

        $response = $this->actingAs($this->admin)->post('/admin/orders/bulk-confirm', [
            'order_ids' => [$this->order->id, $order2->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->order->refresh();
        $order2->refresh();

        $this->assertEquals(Order::STATUS_CONFIRMED, $this->order->status);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order2->status);
    }

    public function test_admin_can_print_multiple_bag_tickets_with_barcodes(): void
    {
        $this->assertEquals(0, $this->order->ticket_print_count);
        $this->assertNull($this->order->ticket_printed_at);

        $response = $this->actingAs($this->admin)->get("/admin/orders/bag-tickets?order_ids={$this->order->id}");

        $response->assertOk();
        $this->assertStringContainsString('BAG TICKET', $response->getContent());
        $this->assertStringContainsString('E-Commerce Dispatch Hub', $response->getContent());
        $this->assertStringContainsString($this->order->order_number, $response->getContent());
        $this->assertStringContainsString('Naveed Iqbal', $response->getContent());
        $this->assertStringContainsString('01711998877', $response->getContent());
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringContainsString('Print #1', $response->getContent());

        $this->order->refresh();
        $this->assertEquals(1, $this->order->ticket_print_count);
        $this->assertNotNull($this->order->ticket_printed_at);

        // Printing a second time should increment count to 2 and show REPRINT badge
        $secondResponse = $this->actingAs($this->admin)->get("/admin/orders/bag-tickets?order_ids={$this->order->id}");
        $secondResponse->assertOk();
        $this->assertStringContainsString('REPRINT #2', $secondResponse->getContent());

        $this->order->refresh();
        $this->assertEquals(2, $this->order->ticket_print_count);
    }

    public function test_admin_can_bulk_transition_confirmed_orders_to_processing(): void
    {
        $this->order->update(['status' => Order::STATUS_CONFIRMED]);

        $response = $this->actingAs($this->admin)->post('/admin/orders/bulk-transition', [
            'order_ids' => [$this->order->id],
            'target_status' => Order::STATUS_PROCESSING,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_PROCESSING, $this->order->status);
    }

    public function test_admin_can_bulk_transition_processing_orders_to_with_courier(): void
    {
        $this->order->update(['status' => Order::STATUS_PROCESSING]);

        $response = $this->actingAs($this->admin)->post('/admin/orders/bulk-transition', [
            'order_ids' => [$this->order->id],
            'target_status' => Order::STATUS_HANDED_TO_COURIER,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->order->refresh();
        $this->assertEquals(Order::STATUS_HANDED_TO_COURIER, $this->order->status);
    }
}
