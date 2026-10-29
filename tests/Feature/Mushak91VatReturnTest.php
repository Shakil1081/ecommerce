<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Procurement\Models\BillOfEntry;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\TaxCompliance\Actions\GenerateMushak63InvoiceAction;
use App\Modules\TaxCompliance\Actions\GenerateMushak91ReturnAction;
use App\Modules\TaxCompliance\Actions\SubmitMushak91ReturnAction;
use App\Modules\TaxCompliance\Models\Mushak91Return;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class Mushak91VatReturnTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    private Supplier $localSupplier;

    private Supplier $internationalSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        $division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA',
        ]);

        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-D',
            'zone_type' => 'inside_dhaka',
        ]);

        $category = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Walton BD',
            'slug' => 'walton-bd',
            'origin_country' => 'Bangladesh',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Inverter Refrigerator',
            'slug' => 'inverter-refrigerator',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '46000.00',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'W-REF-INV-01',
            'attributes' => ['Color' => 'Silver', 'Capacity' => '320L'],
            'selling_price_bdt' => '46000.00',
            'purchase_cost_bdt' => '35000.00',
            'is_active' => true,
        ]);

        $this->localSupplier = Supplier::create([
            'code' => 'SUP-LOC-WLT',
            'name' => 'Walton Hi-Tech Industries PLC',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001234567-0101',
            'is_active' => true,
        ]);

        $this->internationalSupplier = Supplier::create([
            'code' => 'SUP-INT-LG',
            'name' => 'LG Electronics Asia Ltd',
            'type' => 'international',
            'country_code' => 'KR',
            'currency' => 'USD',
            'is_active' => true,
        ]);
    }

    public function test_compiles_outward_supplies_and_input_tax_credit_accurately(): void
    {
        $taxPeriod = '2026-10';

        // 1. Create a Sales Order with Mushak 6.3 Invoice in October 2026
        // Total 46,000 (inclusive of 15% VAT => exclusive = 40,000, VAT = 6,000)
        $order = Order::create([
            'order_number' => 'ORD-202610-001',
            'customer_name' => 'Hasan Mahmud',
            'customer_phone' => '01812345678',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Banani, Dhaka',
            'division_id' => 1,
            'district_id' => 1,
            'subtotal_bdt' => '46000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '0.0000',
            'total_bdt' => '46000.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '46000.0000',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => (string) $this->product->id,
            'product_variant_id' => (string) $this->variant->id,
            'product_name' => $this->product->name,
            'sku' => $this->variant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '46000.0000',
            'tier_savings_bdt' => '0.0000',
            'subtotal_bdt' => '46000.0000',
            'unit_cogs_bdt' => '35000.0000',
        ]);

        $generateInvoiceAction = app(GenerateMushak63InvoiceAction::class);
        $generateInvoiceAction($order, [
            'tax_period' => $taxPeriod,
            'issue_date' => CarbonImmutable::parse('2026-10-15 11:00:00'),
            'vat_rate_percent' => 15.00,
            'prices_include_vat' => true,
        ]);

        // 2. Create a Local PO in October 2026 with Supplier Mushak 6.3
        // Subtotal = 20,000, VAT = 3,000 (rebatable input tax), VDS = 1,000
        LocalPurchaseOrder::create([
            'po_number' => 'PO-LOC-202610-01',
            'supplier_id' => $this->localSupplier->id,
            'order_date' => '2026-10-10',
            'status' => 'received',
            'mushak_6_3_invoice_number' => 'M6.3-WLT-99281',
            'mushak_6_3_invoice_date' => '2026-10-10',
            'subtotal_bdt' => '20000.0000',
            'vat_amount_bdt' => '3000.0000',
            'vds_rate_percent' => '5.00',
            'vds_amount_bdt' => '1000.0000',
            'tds_rate_percent' => '3.00',
            'tds_amount_bdt' => '600.0000',
            'total_payable_to_supplier_bdt' => '21400.0000',
        ]);

        // 3. Create an Import Purchase Order + BOE in October 2026
        // Assessment = 10,000, VAT Import = 1,500, Advance Tax (AT) = 500
        $importPo = ImportPurchaseOrder::create([
            'po_number' => 'IPO-202610-01',
            'supplier_id' => $this->internationalSupplier->id,
            'order_date' => '2026-10-05',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => '120.00',
            'total_fob_foreign' => '1000.00',
            'total_fob_bdt' => '120000.00',
            'status' => 'received',
        ]);

        BillOfEntry::create([
            'import_purchase_order_id' => $importPo->id,
            'boe_number' => 'BOE-CTG-202610-881',
            'boe_date' => '2026-10-12',
            'customs_station' => 'Chattogram Custom House',
            'customs_assessment_value_bdt' => '10000.0000',
            'customs_duty_cd' => '2500.0000',
            'regulatory_duty_rd' => '300.0000',
            'supplementary_duty_sd' => '0.0000',
            'vat_import' => '1500.0000',
            'advance_tax_at' => '500.0000',
            'advance_income_tax_ait' => '500.0000',
            'customs_fine_penalty' => '0.0000',
            'total_customs_duties_bdt' => '5300.0000',
            'payment_status' => 'paid',
            'assessment_status' => 'out_of_charge',
        ]);

        // Generate Mushak 9.1
        $generator = app(GenerateMushak91ReturnAction::class);
        $return = $generator($taxPeriod);

        $this->assertInstanceOf(Mushak91Return::class, $return);
        $this->assertSame($taxPeriod, $return->tax_period);
        $this->assertSame('finalized', $return->status);

        // Part 3: Output VAT = 6,000
        $this->assertEquals(40000.00, (float) $return->sales_standard_rated_value_bdt);
        $this->assertEquals(6000.00, (float) $return->sales_standard_rated_vat_bdt);
        $this->assertEquals(6000.00, (float) $return->total_output_vat_bdt);

        // Part 4: Input Tax Credit
        // Local VAT = 3,000 + Import VAT = 1,500 + Import AT (5%) = 500 => Total Input Credit = 5,000
        $this->assertEquals(20000.00, (float) $return->local_purchase_value_bdt);
        $this->assertEquals(3000.00, (float) $return->local_purchase_vat_bdt);
        $this->assertEquals(10000.00, (float) $return->import_assessment_value_bdt);
        $this->assertEquals(1500.00, (float) $return->import_vat_rebatable_bdt);
        $this->assertEquals(500.00, (float) $return->import_advance_tax_at_bdt);
        $this->assertEquals(5000.00, (float) $return->total_input_tax_credit_bdt);

        // Part 5: Increasing Adjustment for VDS deducted = 1,000
        $this->assertEquals(1000.00, (float) $return->increasing_adjustments_vds_bdt);

        // Part 6: Net Tax Math:
        // Output (6000) - Input Credit (5000) + VDS Increasing Adjustment (1000) = 2000 Net Payable
        $this->assertEquals(2000.00, (float) $return->net_vat_payable_bdt);
        $this->assertEquals(0.00, (float) $return->closing_balance_credit_bdt);
        $this->assertTrue($return->isPayable());
    }

    public function test_computes_refundable_credit_when_input_exceeds_output(): void
    {
        $taxPeriod = '2026-11';

        // Heavy Import Month, zero sales yet
        $importPo = ImportPurchaseOrder::create([
            'po_number' => 'IPO-202611-01',
            'supplier_id' => $this->internationalSupplier->id,
            'order_date' => '2026-11-01',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => '120.00',
            'total_fob_foreign' => '5000.00',
            'total_fob_bdt' => '600000.00',
            'status' => 'received',
        ]);

        BillOfEntry::create([
            'import_purchase_order_id' => $importPo->id,
            'boe_number' => 'BOE-CTG-202611-999',
            'boe_date' => '2026-11-05',
            'customs_station' => 'Chattogram Custom House',
            'customs_assessment_value_bdt' => '500000.0000',
            'customs_duty_cd' => '125000.0000',
            'regulatory_duty_rd' => '15000.0000',
            'supplementary_duty_sd' => '0.0000',
            'vat_import' => '75000.0000',
            'advance_tax_at' => '25000.0000',
            'advance_income_tax_ait' => '25000.0000',
            'customs_fine_penalty' => '0.0000',
            'total_customs_duties_bdt' => '265000.0000',
            'payment_status' => 'paid',
            'assessment_status' => 'out_of_charge',
        ]);

        $generator = app(GenerateMushak91ReturnAction::class);
        $return = $generator($taxPeriod);

        // Output VAT = 0, Input Credit = 75,000 + 25,000 = 100,000
        $this->assertEquals(0.00, (float) $return->total_output_vat_bdt);
        $this->assertEquals(100000.00, (float) $return->total_input_tax_credit_bdt);
        $this->assertEquals(0.00, (float) $return->net_vat_payable_bdt);
        $this->assertEquals(100000.00, (float) $return->closing_balance_credit_bdt);
        $this->assertFalse($return->isPayable());
    }

    public function test_submit_mushak_9_1_return_action(): void
    {
        $generator = app(GenerateMushak91ReturnAction::class);
        $submitter = app(SubmitMushak91ReturnAction::class);

        $return = $generator('2026-12');
        $this->assertFalse($return->isSubmitted());

        $submitted = $submitter($return, [
            'treasury_challan_number' => 'TR-CH-2026-99120',
            'treasury_challan_date' => '2026-12-14',
            'treasury_deposit_amount_bdt' => '0.0000',
        ]);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertSame('submitted', $submitted->status);
        $this->assertStringStartsWith('NBR-9.1-202612-', $submitted->submission_reference);
        $this->assertSame('TR-CH-2026-99120', $submitted->treasury_challan_number);

        // Recalculating without force fails
        $this->expectException(InvalidArgumentException::class);
        $generator('2026-12');
    }
}
