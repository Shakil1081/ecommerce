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
}
