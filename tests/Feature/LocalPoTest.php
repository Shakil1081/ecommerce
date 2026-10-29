<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Procurement\Actions\IssueMushak66Action;
use App\Modules\Procurement\Actions\VerifySupplierBinAction;
use App\Modules\Procurement\Models\LocalPoItem;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LocalPoTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'code' => 'SUP-LOC-001',
            'name' => 'Meghna Traders PLC',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001234567-0101',
            'tin_number' => '987654321012',
            'is_vds_applicable' => true,
            'is_tds_applicable' => true,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Walton',
            'slug' => 'walton',
            'country_of_origin' => 'BD',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Smart Inverter Blender',
            'slug' => 'smart-inverter-blender',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'hs_code' => '8509.40.00',
            'origin_country' => 'Bangladesh',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SIB-RED',
            'barcode_ean' => '8942233445566',
            'attributes' => ['color' => 'Crimson Red'],
            'purchase_cost_bdt' => '2500.0000',
            'selling_price_bdt' => '3800.0000',
            'is_active' => true,
        ]);
    }

    public function test_can_create_local_purchase_order_and_compute_vds_and_tds_withholdings(): void
    {
        $po = LocalPurchaseOrder::create([
            'po_number' => 'LPO-2026-0001',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-10-20',
            'delivery_deadline' => '2026-10-25',
            'status' => 'issued',
            'mushak_6_3_invoice_number' => 'M63-MEGHNA-9982',
            'mushak_6_3_invoice_date' => '2026-10-22',
            'vds_rate_percent' => '5.00', // 5% VDS
            'tds_rate_percent' => '2.00', // 2% TDS under Income Tax Act 2023
            'payment_terms' => 'Mushak 6.3 on delivery, Net 15 days',
        ]);

        $this->assertDatabaseHas('local_purchase_orders', [
            'id' => $po->id,
            'po_number' => 'LPO-2026-0001',
            'mushak_6_3_invoice_number' => 'M63-MEGHNA-9982',
        ]);

        $this->assertNotEmpty($po->ulid);

        // Add Item: 100 units at ৳2,500.00 = ৳250,000.00 Subtotal + 15% VAT (৳37,500.00) = ৳287,500.00
        $item = LocalPoItem::create([
            'local_purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 100,
            'unit_price_bdt' => '2500.0000',
            'subtotal_bdt' => '250000.0000',
            'vat_rate_percent' => '15.00',
            'vat_amount_bdt' => '37500.0000',
            'total_item_cost_bdt' => '287500.0000',
        ]);

        $po->load('items');
        $po->recalculateTotals();

        $fresh = $po->fresh();
        $this->assertEquals('250000.0000', (string) $fresh->subtotal_bdt);
        $this->assertEquals('37500.0000', (string) $fresh->vat_amount_bdt);

        // VDS 5% on ৳250,000.00 = ৳12,500.00
        $this->assertEquals('12500.0000', (string) $fresh->vds_amount_bdt);

        // TDS 2% on ৳250,000.00 = ৳5,000.00
        $this->assertEquals('5000.0000', (string) $fresh->tds_amount_bdt);

        // Net Payable = 250,000 + 37,500 - 12,500 - 5,000 = ৳270,000.00
        $this->assertEquals('270000.0000', (string) $fresh->total_payable_to_supplier_bdt);
    }

    public function test_can_issue_mushak_6_6_vds_certificate_for_local_procurement(): void
    {
        $po = LocalPurchaseOrder::create([
            'po_number' => 'LPO-2026-0002',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-10-21',
            'status' => 'received',
            'mushak_6_3_invoice_number' => 'M63-MEGHNA-9983',
            'mushak_6_3_invoice_date' => '2026-10-23',
            'subtotal_bdt' => '100000.0000',
            'vat_amount_bdt' => '15000.0000',
            'vds_rate_percent' => '5.00',
            'vds_amount_bdt' => '5000.0000',
            'total_payable_to_supplier_bdt' => '110000.0000',
        ]);

        $issuer = new IssueMushak66Action(new VerifySupplierBinAction);
        $cert = $issuer($po, '2026-10-24');

        $this->assertStringStartsWith('VDS-2026-', $cert['certificate_number']);
        $this->assertEquals('2026-10-24', $cert['issue_date']);
        $this->assertEquals('0012345670101', $cert['supplier_bin']);
        $this->assertEquals('5000.0000', $cert['vds_amount_bdt']);
        $this->assertEquals($cert['certificate_number'], $po->fresh()->mushak_6_6_certificate_number);
    }

    public function test_cannot_issue_mushak_6_6_if_supplier_has_invalid_bin(): void
    {
        $invalidSupplier = Supplier::create([
            'code' => 'SUP-INVALID',
            'name' => 'Unregistered Vendor',
            'type' => 'local',
            'bin_number' => '1234', // Invalid BIN
            'is_active' => true,
        ]);

        $po = LocalPurchaseOrder::create([
            'po_number' => 'LPO-2026-0003',
            'supplier_id' => $invalidSupplier->id,
            'order_date' => '2026-10-21',
            'status' => 'received',
            'vds_amount_bdt' => '5000.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot issue Mushak 6.6 Certificate: Supplier does not possess a valid 9 or 13-digit NBR BIN.');

        $issuer = new IssueMushak66Action(new VerifySupplierBinAction);
        $issuer($po);
    }
}
