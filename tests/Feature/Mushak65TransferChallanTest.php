<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\Inventory\Models\WarehouseTransferItem;
use App\Modules\TaxCompliance\Actions\GenerateMushak65ChallanAction;
use App\Modules\TaxCompliance\Models\Mushak65Challan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class Mushak65TransferChallanTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $centralHub;

    private Warehouse $chattogramDepot;

    private Product $product;

    private ProductVariant $variant;

    private WarehouseTransfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralHub = Warehouse::create([
            'name' => 'Dhaka Central Hub',
            'code' => 'CENTRAL-DHA',
            'address' => 'Plot 10, Tejgaon I/A, Dhaka-1208',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->chattogramDepot = Warehouse::create([
            'name' => 'Chattogram Regional Depot',
            'code' => 'DEPOT-CTG',
            'address' => 'Agrabad Commercial Area, Chattogram',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Consumer Electronics',
            'slug' => 'consumer-electronics',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'DJI Global',
            'slug' => 'dji-global',
            'origin_country' => 'China',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'DJI Mini 4 Pro Drone',
            'slug' => 'dji-mini-4-pro-drone',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '125000.00',
            'hs_code' => '8806.22.00',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'DJI-M4P-COMBO',
            'attributes' => ['Edition' => 'Fly More Combo Plus'],
            'selling_price_bdt' => '125000.00',
            'purchase_cost_bdt' => '95000.00',
            'is_active' => true,
        ]);

        $this->transfer = WarehouseTransfer::create([
            'transfer_number' => 'TRF-202610-001',
            'mushak_6_5_serial' => 'M65-PENDING-001',
            'source_warehouse_id' => $this->centralHub->id,
            'destination_warehouse_id' => $this->chattogramDepot->id,
            'status' => WarehouseTransfer::STATUS_IN_TRANSIT,
            'vehicle_number' => 'Dhaka Metro-U-14-9902',
            'driver_name' => 'Mohammad Ali',
            'driver_contact' => '01719998877',
            'dispatched_at' => CarbonImmutable::parse('2026-10-25 08:30:00'),
        ]);

        WarehouseTransferItem::create([
            'transfer_id' => $this->transfer->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 5,
        ]);
    }

    public function test_generates_statutory_mushak_6_5_transfer_challan(): void
    {
        $action = app(GenerateMushak65ChallanAction::class);
        $issueDate = CarbonImmutable::parse('2026-10-25 08:30:00');

        $result = $action($this->transfer, [
            'issue_date' => $issueDate,
        ]);

        $this->assertFalse($result->isExisting);
        $this->assertSame('M6.5-202610-000001', $result->challanNumber);
        $this->assertSame('Dhaka Central Hub', $result->sourceWarehouseName);
        $this->assertSame('Chattogram Regional Depot', $result->destinationWarehouseName);
        $this->assertSame(5, $result->totalQuantity);
        // 5 units @ 95,000 = 475,000
        $this->assertEquals(475000.00, (float) $result->totalValuationBdt);

        $challan = $result->challan;
        $this->assertInstanceOf(Mushak65Challan::class, $challan);
        $this->assertSame($this->transfer->id, $challan->warehouse_transfer_id);
        $this->assertSame('Dhaka Metro-U-14-9902', $challan->vehicle_type_and_number);
        $this->assertSame('Mohammad Ali', $challan->driver_name);
        $this->assertSame('dispatched', $challan->status);
        $this->assertNotEmpty($challan->integrity_hash);

        $this->assertCount(1, $challan->items);
        $item = $challan->items->first();
        $this->assertSame('DJI Mini 4 Pro Drone', $item->item_name);
        $this->assertSame('8806.22.00', $item->hs_code);
        $this->assertSame(5, $item->quantity);
        $this->assertEquals(95000.00, (float) $item->unit_cost_bdt);
        $this->assertEquals(475000.00, (float) $item->total_valuation_bdt);

        // Parent Transfer relationship and reference
        $this->transfer->refresh();
        $this->assertSame('M6.5-202610-000001', $this->transfer->mushak_6_5_serial);
        $this->assertNotNull($this->transfer->mushak65Challan);
        $this->assertSame($challan->id, $this->transfer->mushak65Challan->id);
    }

    public function test_generation_is_idempotent(): void
    {
        $action = app(GenerateMushak65ChallanAction::class);

        $res1 = $action($this->transfer);
        $res2 = $action($this->transfer);

        $this->assertFalse($res1->isExisting);
        $this->assertTrue($res2->isExisting);
        $this->assertSame($res1->challanNumber, $res2->challanNumber);
        $this->assertSame(1, Mushak65Challan::where('warehouse_transfer_id', $this->transfer->id)->count());
    }

    public function test_fails_if_source_and_destination_warehouses_are_same(): void
    {
        $sameTransfer = WarehouseTransfer::create([
            'transfer_number' => 'TRF-SAME-01',
            'mushak_6_5_serial' => 'M65-SAME-001',
            'source_warehouse_id' => $this->centralHub->id,
            'destination_warehouse_id' => $this->centralHub->id, // identical
            'status' => WarehouseTransfer::STATUS_DRAFT,
        ]);

        WarehouseTransferItem::create([
            'transfer_id' => $sameTransfer->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination warehouses must be different');

        app(GenerateMushak65ChallanAction::class)($sameTransfer);
    }

    public function test_fails_if_transfer_contains_no_items(): void
    {
        $emptyTransfer = WarehouseTransfer::create([
            'transfer_number' => 'TRF-EMPTY-01',
            'mushak_6_5_serial' => 'M65-EMPTY-001',
            'source_warehouse_id' => $this->centralHub->id,
            'destination_warehouse_id' => $this->chattogramDepot->id,
            'status' => WarehouseTransfer::STATUS_DRAFT,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transfer contains no items');

        app(GenerateMushak65ChallanAction::class)($emptyTransfer);
    }
}
