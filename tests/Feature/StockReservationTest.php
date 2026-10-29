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
use App\Modules\Inventory\Actions\CommitStockReservationAction;
use App\Modules\Inventory\Actions\ReleaseExpiredStockReservationsAction;
use App\Modules\Inventory\Actions\ReleaseStockReservationAction;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Actions\CreateOrderFromCartAction;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private Order $order;

    private ReserveStockForOrderAction $reserveStock;

    private CommitStockReservationAction $commitStock;

    private ReleaseStockReservationAction $releaseStock;

    private ReleaseExpiredStockReservationsAction $releaseExpiredStock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub Dhaka',
            'code' => 'WH-DHA-01',
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
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'country_of_origin' => 'US',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'iPhone 16 Pro',
            'slug' => 'iphone-16-pro',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'USA',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IPH16P-256-NAT',
            'barcode_ean' => '195949000001',
            'attributes' => ['color' => 'Natural Titanium', 'storage' => '256GB'],
            'purchase_cost_bdt' => '130000.0000',
            'selling_price_bdt' => '155000.0000',
            'is_active' => true,
        ]);

        // Seed inventory stock in primary warehouse: 50 physical, 0 reserved
        InventoryStock::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'physical_quantity' => 50,
            'reserved_quantity' => 0,
            'bin_location' => 'AISLE-1-RACK-A',
        ]);

        // Create an order with 5 units
        $getCart = app(GetActiveCartAction::class);
        $addToCart = app(AddToCartAction::class);
        $createOrder = app(CreateOrderFromCartAction::class);

        $cart = $getCart($this->user);
        $cart = $addToCart($cart, $this->variant, 5);

        $this->order = $createOrder($cart, [
            'customer_name' => 'Kashif Alam',
            'customer_phone' => '01700112233',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_address' => 'Banani, Dhaka',
        ], $this->user);

        $this->reserveStock = app(ReserveStockForOrderAction::class);
        $this->commitStock = app(CommitStockReservationAction::class);
        $this->releaseStock = app(ReleaseStockReservationAction::class);
        $this->releaseExpiredStock = app(ReleaseExpiredStockReservationsAction::class);
    }

    public function test_can_reserve_stock_atomically_for_order(): void
    {
        $reservations = ($this->reserveStock)($this->order, $this->warehouse, 15);

        $this->assertCount(1, $reservations);
        $reservation = $reservations->first();
        $this->assertEquals(StockReservation::STATUS_RESERVED, $reservation->status);
        $this->assertEquals(5, $reservation->quantity);
        $this->assertEquals($this->warehouse->id, $reservation->warehouse_id);
        $this->assertEquals($this->variant->id, $reservation->product_variant_id);

        // Check stock state: physical 50, reserved 5, available 45
        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertEquals(50, $stock->physical_quantity);
        $this->assertEquals(5, $stock->reserved_quantity);
        $this->assertEquals(45, $stock->available_quantity);
    }

    public function test_insufficient_stock_throws_exception_and_prevents_oversell(): void
    {
        // Reduce physical stock to 3 units (less than order's 5 units)
        InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->update(['physical_quantity' => 3]);

        $this->expectException(InsufficientStockException::class);
        ($this->reserveStock)($this->order, $this->warehouse);

        // Reserved count remains 0
        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertEquals(0, $stock->reserved_quantity);
    }

    public function test_flash_sale_concurrency_protection_blocks_subsequent_order(): void
    {
        // Set exact stock of 6 units
        InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->update(['physical_quantity' => 6, 'reserved_quantity' => 0]);

        // Order 1 takes 5 units -> 1 left available
        ($this->reserveStock)($this->order, $this->warehouse);

        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(1, $stock->available_quantity);

        // Order 2 tries to reserve another 5 units -> MUST FAIL
        $this->expectException(InsufficientStockException::class);
        ($this->reserveStock)($this->order, $this->warehouse);
    }

    public function test_commit_stock_reservation_deducts_physical_and_reserved_stock(): void
    {
        ($this->reserveStock)($this->order, $this->warehouse);

        // Commit reservation
        ($this->commitStock)($this->order);

        $reservation = StockReservation::where('order_id', $this->order->id)->first();
        $this->assertEquals(StockReservation::STATUS_COMMITTED, $reservation->status);

        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        // 50 - 5 = 45 physical, 0 reserved, 45 available
        $this->assertEquals(45, $stock->physical_quantity);
        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(45, $stock->available_quantity);
    }

    public function test_release_stock_reservation_restores_available_stock(): void
    {
        ($this->reserveStock)($this->order, $this->warehouse);

        // Release reservation
        ($this->releaseStock)($this->order);

        $reservation = StockReservation::where('order_id', $this->order->id)->first();
        $this->assertEquals(StockReservation::STATUS_RELEASED, $reservation->status);

        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        // Reserved back to 0, physical intact at 50, available back to 50
        $this->assertEquals(50, $stock->physical_quantity);
        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(50, $stock->available_quantity);
    }

    public function test_release_expired_stock_reservations_action_and_artisan_command(): void
    {
        // Create 2 expired reservations in past
        StockReservation::create([
            'order_id' => $this->order->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'status' => StockReservation::STATUS_RESERVED,
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        StockReservation::create([
            'order_id' => $this->order->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'status' => StockReservation::STATUS_RESERVED,
            'expires_at' => Carbon::now()->subMinutes(1),
        ]);

        // Set reserved_quantity = 15
        InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->update(['reserved_quantity' => 15]);

        // Run artisan command
        $this->artisan('inventory:release-expired-reservations')
            ->expectsOutputToContain('Successfully released 2 expired inventory reservation(s).')
            ->assertSuccessful();

        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(50, $stock->available_quantity);
    }
}
