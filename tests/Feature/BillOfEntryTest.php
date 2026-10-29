<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Procurement\Models\BillOfEntry;
use App\Modules\Procurement\Models\BoeItem;
use App\Modules\Procurement\Models\ImportPoItem;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillOfEntryTest extends TestCase
{
    use RefreshDatabase;

    private ImportPurchaseOrder $order;

    private ImportPoItem $poItem;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $supplier = Supplier::create([
            'code' => 'SUP-INT-001',
            'name' => 'Shenzhen Electronics Ltd',
            'type' => 'international',
            'country_code' => 'CN',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'SoundPro',
            'slug' => 'soundpro',
            'country_of_origin' => 'CN',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Noise Cancelling Headphones',
            'slug' => 'noise-cancelling-headphones',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'hs_code' => '8518.30.00',
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'NCH-001',
            'barcode_ean' => '8941122334455',
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '3675.0000',
            'selling_price_bdt' => '5500.0000',
            'is_active' => true,
        ]);

        $this->order = ImportPurchaseOrder::create([
            'po_number' => 'IPO-2026-0001',
            'supplier_id' => $supplier->id,
            'status' => 'customs_clearing',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => '122.5000',
            'port_of_discharge' => 'Chattogram',
        ]);

        $this->poItem = ImportPoItem::create([
            'import_purchase_order_id' => $this->order->id,
            'product_id' => $product->id,
            'product_variant_id' => $this->variant->id,
            'hs_code' => '8518.30.00',
            'quantity_ordered' => 100,
            'unit_price_foreign' => '30.0000',
            'unit_price_bdt' => '3675.0000',
            'total_fob_foreign' => '3000.0000',
            'total_fob_bdt' => '367500.0000',
        ]);
    }

    public function test_can_create_bill_of_entry_and_compute_statutory_customs_tariffs(): void
    {
        $boe = BillOfEntry::create([
            'import_purchase_order_id' => $this->order->id,
            'boe_number' => 'C-109283-2026',
            'boe_date' => '2026-10-25',
            'customs_station' => 'chattogram_port',
            'c_and_f_agent_name' => 'Bengal C&F Agency Ltd',
            'c_and_f_license_number' => 'CF-CTG-2018-091',
            'assessment_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('bills_of_entry', [
            'id' => $boe->id,
            'boe_number' => 'C-109283-2026',
            'customs_station' => 'chattogram_port',
        ]);

        $this->assertNotEmpty($boe->ulid);

        // Add BOE item with AV = ৳100,000.00
        $boeItem = BoeItem::create([
            'bill_of_entry_id' => $boe->id,
            'import_po_item_id' => $this->poItem->id,
            'product_variant_id' => $this->variant->id,
            'hs_code' => '8518.30.00',
            'item_description' => 'Noise Cancelling Headphones (100 pcs)',
            'quantity' => 100,
            'customs_assessment_value_bdt' => '100000.0000',
            'cd_rate_percent' => '15.00',
            'rd_rate_percent' => '3.00',
            'sd_rate_percent' => '10.00',
            'vat_rate_percent' => '15.00',
            'at_rate_percent' => '5.00',
            'ait_rate_percent' => '5.00',
        ]);

        // Compute statutory tariffs
        $boeItem->computeTariffs();

        $freshItem = $boeItem->fresh();
        $this->assertEquals('15000.0000', (string) $freshItem->cd_amount_bdt);
        $this->assertEquals('3000.0000', (string) $freshItem->rd_amount_bdt);
        $this->assertEquals('11800.0000', (string) $freshItem->sd_amount_bdt);
        $this->assertEquals('19470.0000', (string) $freshItem->vat_amount_bdt);
        $this->assertEquals('5000.0000', (string) $freshItem->at_amount_bdt); // Advance Tax (AT - 5%)
        $this->assertEquals('5000.0000', (string) $freshItem->ait_amount_bdt); // Advance Income Tax (AIT - 5%)
        $this->assertEquals('59270.0000', (string) $freshItem->total_duty_bdt);

        // Header recalculation
        $boe->load('items');
        $boe->recalculateDuties();

        $freshBoe = $boe->fresh();
        $this->assertEquals('100000.0000', (string) $freshBoe->customs_assessment_value_bdt);
        $this->assertEquals('15000.0000', (string) $freshBoe->customs_duty_cd);
        $this->assertEquals('5000.0000', (string) $freshBoe->advance_tax_at);
        $this->assertEquals('59270.0000', (string) $freshBoe->total_customs_duties_bdt);
    }

    public function test_can_transition_boe_to_out_of_charge_with_payment(): void
    {
        $boe = BillOfEntry::create([
            'import_purchase_order_id' => $this->order->id,
            'boe_number' => 'C-209182-2026',
            'boe_date' => '2026-10-26',
            'customs_station' => 'chattogram_port',
            'assessment_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $this->assertEquals(1, BillOfEntry::pending()->count());
        $this->assertEquals(0, BillOfEntry::outOfCharge()->count());

        $boe->update([
            'payment_status' => 'paid',
            'customs_payment_date' => '2026-10-27',
            'customs_challan_number' => 'TR-CTG-2026-88129',
            'assessment_status' => 'out_of_charge',
            'out_of_charge_date' => '2026-10-28',
        ]);

        $this->assertEquals(0, BillOfEntry::pending()->count());
        $this->assertEquals(1, BillOfEntry::outOfCharge()->count());
        $this->assertEquals('paid', $boe->fresh()->payment_status);
        $this->assertEquals('out_of_charge', $boe->fresh()->assessment_status);
    }
}
