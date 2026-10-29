<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Actions\ReceiveGoodsNoteAction;
use App\Modules\Procurement\Models\GoodsReceivedNote;
use App\Modules\Procurement\Models\GrnItem;
use App\Modules\Procurement\Models\ImportPoItem;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\LocalPoItem;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GoodsReceivedNoteTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private User $user;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub Gazipur',
            'code' => 'HUB-GZP-01',
            'address' => 'Plot 10, Joydebpur Road, Gazipur',
            'is_central' => true,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Smart Home',
            'slug' => 'smart-home',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'HomeTech',
            'slug' => 'hometech',
            'country_of_origin' => 'CN',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Smart Wi-Fi Security Camera',
            'slug' => 'smart-wifi-security-camera',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SWC-1080P',
            'barcode_ean' => '8941092837465',
            'attributes' => ['resolution' => '1080p'],
            'purchase_cost_bdt' => '2200.0000',
            'selling_price_bdt' => '3900.0000',
            'is_active' => true,
        ]);
    }

    public function test_can_receive_import_goods_and_atomically_update_warehouse_inventory(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-INT-001',
            'name' => 'Hikvision Global',
            'type' => 'international',
            'is_active' => true,
        ]);

        $importPo = ImportPurchaseOrder::create([
            'po_number' => 'IPO-2026-8801',
            'supplier_id' => $supplier->id,
            'status' => 'customs_clearing',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => '122.5000',
            'total_fob_bdt' => '220000.0000',
        ]);

        $poItem = ImportPoItem::create([
            'import_purchase_order_id' => $importPo->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 100,
            'quantity_received' => 0,
            'unit_price_foreign' => '18.0000',
            'unit_price_bdt' => '2205.0000',
            'total_fob_foreign' => '1800.0000',
            'total_fob_bdt' => '220500.0000',
        ]);

        // Create GRN
        $grn = GoodsReceivedNote::create([
            'grn_number' => 'GRN-2026-0001',
            'grn_date' => '2026-10-27',
            'source_type' => 'import',
            'import_purchase_order_id' => $importPo->id,
            'warehouse_id' => $this->warehouse->id,
            'received_by_user_id' => $this->user->id,
            'truck_or_container_number' => 'DM-TA-11-2091',
            'driver_name' => 'Kamal Hossain',
            'status' => 'draft',
        ]);

        GrnItem::create([
            'goods_received_note_id' => $grn->id,
            'import_po_item_id' => $poItem->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'quantity_damaged' => 0,
            'barcode_scanned' => '8941092837465',
            'bin_location' => 'AISLE-3-SHELF-B1',
        ]);

        $action = new ReceiveGoodsNoteAction;
        $processedGrn = $action($grn);

        $this->assertEquals('completed', $processedGrn->status);
        $this->assertEquals(100, $processedGrn->total_received_quantity);
        $this->assertEquals(0, $processedGrn->total_damaged_quantity);

        // Verify physical inventory stock
        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(100, $stock->physical_quantity);
        $this->assertEquals('AISLE-3-SHELF-B1', $stock->bin_location);

        // Verify PO item received count and PO status
        $this->assertEquals(100, $poItem->fresh()->quantity_received);
        $this->assertEquals('received', $importPo->fresh()->status);
    }

    public function test_can_flag_discrepancy_when_damaged_goods_are_detected(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-LOC-001',
            'name' => 'Local Tech BD',
            'type' => 'local',
            'is_active' => true,
        ]);

        $localPo = LocalPurchaseOrder::create([
            'po_number' => 'LPO-2026-8802',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-25',
            'status' => 'issued',
            'subtotal_bdt' => '110000.0000',
        ]);

        $poItem = LocalPoItem::create([
            'local_purchase_order_id' => $localPo->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 50,
            'quantity_received' => 0,
            'unit_price_bdt' => '2200.0000',
            'subtotal_bdt' => '110000.0000',
            'vat_rate_percent' => '15.00',
            'vat_amount_bdt' => '16500.0000',
            'total_item_cost_bdt' => '126500.0000',
        ]);

        $grn = GoodsReceivedNote::create([
            'grn_number' => 'GRN-2026-0002',
            'grn_date' => '2026-10-28',
            'source_type' => 'local',
            'local_purchase_order_id' => $localPo->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        // Received 50, but 5 are damaged in transit
        GrnItem::create([
            'goods_received_note_id' => $grn->id,
            'local_po_item_id' => $poItem->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 50,
            'quantity_received' => 50,
            'quantity_damaged' => 5,
            'discrepancy_reason' => 'Outer carton crushed, lens damaged.',
        ]);

        $action = new ReceiveGoodsNoteAction;
        $processedGrn = $action($grn);

        $this->assertEquals('discrepancy_flagged', $processedGrn->status);
        $this->assertEquals(5, $processedGrn->total_damaged_quantity);

        // Only usable 45 units should be added to stock
        $stock = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertEquals(45, $stock->physical_quantity);
    }

    public function test_cannot_reprocess_already_completed_grn(): void
    {
        $grn = GoodsReceivedNote::create([
            'grn_number' => 'GRN-2026-0003',
            'grn_date' => '2026-10-28',
            'source_type' => 'local',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        GrnItem::create([
            'goods_received_note_id' => $grn->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 10,
            'quantity_received' => 10,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GRN has already been processed and committed to inventory.');

        $action = new ReceiveGoodsNoteAction;
        $action($grn);
    }
}
