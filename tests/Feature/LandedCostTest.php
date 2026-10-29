<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Procurement\Actions\CalculateImportLandedCostAction;
use App\Modules\Procurement\Actions\CalculateLocalLandedCostAction;
use App\Modules\Procurement\Models\BillOfEntry;
use App\Modules\Procurement\Models\ImportPoItem;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\LocalPoItem;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandedCostTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $intSupplier;

    private Supplier $localSupplier;

    private ProductVariant $variantA;

    private ProductVariant $variantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->intSupplier = Supplier::create([
            'code' => 'SUP-INT-001',
            'name' => 'Shenzhen Electronics Ltd',
            'type' => 'international',
            'country_code' => 'CN',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->localSupplier = Supplier::create([
            'code' => 'SUP-LOC-001',
            'name' => 'Dhaka Distribution PLC',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001234567-0101',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Computers & Accessories',
            'slug' => 'computers-accessories',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'NexTech',
            'slug' => 'nextech',
            'country_of_origin' => 'CN',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'name' => 'Mechanical Gaming Keyboard',
            'slug' => 'mechanical-gaming-keyboard',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'MGK-RGB',
            'barcode_ean' => '8941000000001',
            'attributes' => ['switch' => 'Blue'],
            'purchase_cost_bdt' => '2000.0000',
            'selling_price_bdt' => '4500.0000',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'name' => 'Ergonomic Wireless Mouse',
            'slug' => 'ergonomic-wireless-mouse',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'EWM-BLK',
            'barcode_ean' => '8941000000002',
            'attributes' => ['color' => 'Matte Black'],
            'purchase_cost_bdt' => '1000.0000',
            'selling_price_bdt' => '2200.0000',
            'is_active' => true,
        ]);
    }

    public function test_can_calculate_import_landed_cost_and_update_variant_cogs(): void
    {
        $order = ImportPurchaseOrder::create([
            'po_number' => 'IPO-2026-9901',
            'supplier_id' => $this->intSupplier->id,
            'currency' => 'USD',
            'exchange_rate_to_bdt' => '120.0000',
            'total_fob_foreign' => '3000.0000',
            'total_fob_bdt' => '360000.0000',
        ]);

        // Item 1: 100 units at $20 = $2,000 USD = ৳240,000.00 (2/3 of total)
        ImportPoItem::create([
            'import_purchase_order_id' => $order->id,
            'product_id' => $this->variantA->product_id,
            'product_variant_id' => $this->variantA->id,
            'quantity_ordered' => 100,
            'unit_price_foreign' => '20.0000',
            'unit_price_bdt' => '2400.0000',
            'total_fob_foreign' => '2000.0000',
            'total_fob_bdt' => '240000.0000',
        ]);

        // Item 2: 100 units at $10 = $1,000 USD = ৳120,000.00 (1/3 of total)
        ImportPoItem::create([
            'import_purchase_order_id' => $order->id,
            'product_id' => $this->variantB->product_id,
            'product_variant_id' => $this->variantB->id,
            'quantity_ordered' => 100,
            'unit_price_foreign' => '10.0000',
            'unit_price_bdt' => '1200.0000',
            'total_fob_foreign' => '1000.0000',
            'total_fob_bdt' => '120000.0000',
        ]);

        // Customs Bill of Entry: CD ৳36,000 + RD ৳7,200 + SD ৳10,800 = ৳54,000
        $boe = BillOfEntry::create([
            'import_purchase_order_id' => $order->id,
            'boe_number' => 'C-889911-2026',
            'boe_date' => '2026-10-25',
            'customs_duty_cd' => '36000.0000',
            'regulatory_duty_rd' => '7200.0000',
            'supplementary_duty_sd' => '10800.0000',
            'advance_tax_at' => '18000.0000', // Rebatable VAT input credit, not in COGS
            'advance_income_tax_ait' => '18000.0000',
            'total_customs_duties_bdt' => '90000.0000',
        ]);

        // Ancillary expenses: Freight ৳40,000 + Insurance ৳6,000 + C&F ৳15,000 + Carrying ৳5,000 = ৳66,000
        // Total Overheads = 54,000 (Customs CD/RD/SD) + 66,000 (Shipping/C&F) = ৳120,000
        // Total Landed Cost = 360,000 + 120,000 = ৳480,000
        // Item 1 gets 2/3 of 120,000 = ৳80,000 overhead => Total 240,000 + 80,000 = ৳320,000 / 100 = ৳3,200/unit
        // Item 2 gets 1/3 of 120,000 = ৳40,000 overhead => Total 120,000 + 40,000 = ৳160,000 / 100 = ৳1,600/unit

        $action = new CalculateImportLandedCostAction;
        $allocation = $action(
            order: $order,
            boe: $boe,
            overheads: [
                'freight_cost_bdt' => '40000.0000',
                'insurance_cost_bdt' => '6000.0000',
                'port_cnf_cost_bdt' => '15000.0000',
                'transport_carrying_bdt' => '5000.0000',
            ],
            applyToVariants: true
        );

        $this->assertEquals('360000.0000', (string) $allocation->base_purchase_cost_bdt);
        $this->assertEquals('54000.0000', (string) $allocation->customs_duties_bdt);
        $this->assertEquals('480000.0000', (string) $allocation->total_landed_cost_bdt);
        $this->assertEquals('applied_to_inventory', $allocation->status);

        // Check updated variant COGS
        $this->assertEquals('3200.0000', (string) $this->variantA->fresh()->purchase_cost_bdt);
        $this->assertEquals('1600.0000', (string) $this->variantB->fresh()->purchase_cost_bdt);
    }

    public function test_can_calculate_local_landed_cost_and_update_variant_cogs(): void
    {
        $po = LocalPurchaseOrder::create([
            'po_number' => 'LPO-2026-9901',
            'supplier_id' => $this->localSupplier->id,
            'order_date' => '2026-10-22',
            'status' => 'received',
            'subtotal_bdt' => '100000.0000',
            'vat_amount_bdt' => '15000.0000',
        ]);

        // Item 1: 50 units at ৳1,200 = ৳60,000 (60% of subtotal)
        LocalPoItem::create([
            'local_purchase_order_id' => $po->id,
            'product_id' => $this->variantA->product_id,
            'product_variant_id' => $this->variantA->id,
            'quantity_ordered' => 50,
            'unit_price_bdt' => '1200.0000',
            'subtotal_bdt' => '60000.0000',
            'vat_rate_percent' => '15.00',
            'vat_amount_bdt' => '9000.0000',
            'total_item_cost_bdt' => '69000.0000',
        ]);

        // Item 2: 40 units at ৳1,000 = ৳40,000 (40% of subtotal)
        LocalPoItem::create([
            'local_purchase_order_id' => $po->id,
            'product_id' => $this->variantB->product_id,
            'product_variant_id' => $this->variantB->id,
            'quantity_ordered' => 40,
            'unit_price_bdt' => '1000.0000',
            'subtotal_bdt' => '40000.0000',
            'vat_rate_percent' => '15.00',
            'vat_amount_bdt' => '6000.0000',
            'total_item_cost_bdt' => '46000.0000',
        ]);

        // Overheads: Freight ৳8,000 + Handling ৳2,000 = ৳10,000
        // Item 1 gets 60% of 10,000 = ৳6,000 overhead => Total 60,000 + 6,000 = ৳66,000 / 50 = ৳1,320/unit
        // Item 2 gets 40% of 10,000 = ৳4,000 overhead => Total 40,000 + 4,000 = ৳44,000 / 40 = ৳1,100/unit

        $action = new CalculateLocalLandedCostAction;
        $allocation = $action(
            po: $po,
            overheads: [
                'freight_cost_bdt' => '8000.0000',
                'handling_cost_bdt' => '2000.0000',
            ],
            applyToVariants: true
        );

        $this->assertEquals('100000.0000', (string) $allocation->base_purchase_cost_bdt);
        $this->assertEquals('110000.0000', (string) $allocation->total_landed_cost_bdt);
        $this->assertEquals('applied_to_inventory', $allocation->status);

        $this->assertEquals('1320.0000', (string) $this->variantA->fresh()->purchase_cost_bdt);
        $this->assertEquals('1100.0000', (string) $this->variantB->fresh()->purchase_cost_bdt);
    }
}
