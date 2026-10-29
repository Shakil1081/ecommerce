<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Actions\AdjustInventoryStockAction;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WarehouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $centralHub;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralHub = Warehouse::create([
            'name' => 'Dhaka Central Hub',
            'code' => 'CENTRAL-DHAKA',
            'address' => 'Tejgaon Industrial Area, Dhaka',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Computer Peripherals', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Logitech', 'origin_country' => 'Switzerland', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'MX Master 3S Wireless Mouse',
            'hs_code' => '8471.60.90',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'LOGI-MX3S-GRAPHITE',
            'attributes' => ['Color' => 'Graphite'],
            'purchase_cost_bdt' => 8500.00,
            'selling_price_bdt' => 11500.00,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }

    public function test_warehouse_creation_and_primary_scope(): void
    {
        $regionalDepot = Warehouse::create([
            'name' => 'Chattogram Port Depot',
            'code' => 'DEPOT-CTG',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->assertEquals('CENTRAL-DHAKA', Warehouse::primary()->first()?->code);
        $this->assertEquals(2, Warehouse::active()->count());
    }

    public function test_adjust_inventory_stock_action_and_available_calculation(): void
    {
        $adjustAction = new AdjustInventoryStockAction;

        // 1. Initial intake (GRN) of 50 units
        $stock = $adjustAction(
            $this->centralHub,
            $this->variant,
            50,
            'grn_intake',
            'RACK-01-SHELF-A'
        );

        $this->assertEquals(50, $stock->physical_quantity);
        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(50, $stock->available_quantity);
        $this->assertEquals('RACK-01-SHELF-A', $stock->bin_location);
        $this->assertFalse($stock->is_low_stock);

        // 2. Simulate 10 units reserved for pending checkout
        $stock->update(['reserved_quantity' => 10]);
        $stock->refresh();

        $this->assertEquals(50, $stock->physical_quantity);
        $this->assertEquals(10, $stock->reserved_quantity);
        $this->assertEquals(40, $stock->available_quantity);

        // 3. Deduct 46 units from physical count (physical drops to 4, below threshold of 5)
        $adjustAction($this->centralHub, $this->variant, -46, 'cycle_count');
        $stock->refresh();

        $this->assertEquals(4, $stock->physical_quantity);
        $this->assertTrue($stock->is_low_stock);
    }

    public function test_adjust_action_prevents_negative_inventory(): void
    {
        $adjustAction = new AdjustInventoryStockAction;

        // Set initial stock to 5
        $adjustAction($this->centralHub, $this->variant, 5, 'initial');

        // Attempting to deduct 10 units must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient physical stock');

        $adjustAction($this->centralHub, $this->variant, -10, 'order_dispatch');
    }
}
