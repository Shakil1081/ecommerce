<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\TaxCompliance\Actions\GenerateMushak63InvoiceAction;
use App\Modules\TaxCompliance\Actions\GenerateMushak66CertificateAction;
use App\Modules\TaxCompliance\Actions\GenerateMushak91ReturnAction;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TaxComplianceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Order $order;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'tax_admin@example.com',
            'email_verified_at' => now(),
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
            'code' => 'DHA-D',
            'zone_type' => 'inside_dhaka',
        ]);

        $category = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Walton',
            'slug' => 'walton',
            'origin_country' => 'Bangladesh',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Walton Inverter AC 1.5 Ton',
            'slug' => 'walton-inverter-ac-15-ton',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '57500.00',
            'hs_code' => '8415.10.90',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'WLT-AC-15T-INV',
            'attributes' => ['Capacity' => '1.5 Ton', 'Color' => 'White'],
            'selling_price_bdt' => '57500.00',
            'purchase_cost_bdt' => '45000.00',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-202610-TX01',
            'customer_name' => 'Mohammad Shakil',
            'customer_phone' => '01812345678',
            'customer_email' => 'shakil@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Gulshan 2, Dhaka-1212',
            'subtotal_bdt' => '57500.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '0.0000',
            'total_bdt' => '57500.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '57500.0000',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => (string) $this->product->id,
            'product_variant_id' => (string) $this->variant->id,
            'product_name' => $this->product->name,
            'sku' => $this->variant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '57500.0000',
            'tier_savings_bdt' => '0.0000',
            'subtotal_bdt' => '57500.0000',
            'unit_cogs_bdt' => '45000.0000',
        ]);
    }

    public function test_admin_can_view_tax_compliance_dashboard_with_stats(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/tax-compliance');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/TaxComplianceDashboard')
                ->has('stats')
                ->has('recentInvoices')
                ->has('recentReturns')
                ->has('recentRmas')
        );
    }

    public function test_admin_can_view_and_filter_mushak_6_3_invoices(): void
    {
        $generateAction = app(GenerateMushak63InvoiceAction::class);
        $result = $generateAction($this->order);
        $invoice = $result->invoice;

        $response = $this->actingAs($this->admin)->get('/admin/tax-compliance/mushak-6-3');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/Mushak63Index')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.invoice_number', $invoice->invoice_number)
        );

        // Filter by search query
        $filterResponse = $this->actingAs($this->admin)->get('/admin/tax-compliance/mushak-6-3?search='.urlencode($this->order->customer_name));

        $filterResponse->assertStatus(200);
        $filterResponse->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/Mushak63Index')
                ->has('invoices.data', 1)
        );
    }

    public function test_admin_can_view_printable_mushak_6_3_invoice(): void
    {
        $generateAction = app(GenerateMushak63InvoiceAction::class);
        $result = $generateAction($this->order);
        $invoice = $result->invoice;

        $response = $this->actingAs($this->admin)->get("/admin/tax-compliance/mushak-6-3/{$invoice->id}");

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/Mushak63Show')
                ->where('invoice.id', $invoice->id)
                ->where('invoice.invoice_number', $invoice->invoice_number)
                ->has('invoice.items', 1)
        );
    }

    public function test_admin_can_view_mushak_9_1_returns_and_compile_period(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/tax-compliance/mushak-9-1');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/Mushak91Index')
                ->has('returns')
                ->has('currentPeriod')
        );

        // Trigger compile for current period
        $taxPeriod = CarbonImmutable::now()->format('Y-m');
        $compileResponse = $this->actingAs($this->admin)
            ->from('/admin/tax-compliance/mushak-9-1')
            ->post('/admin/tax-compliance/mushak-9-1/compile', [
                'tax_period' => $taxPeriod,
                'force' => true,
            ]);

        $compileResponse->assertRedirect('/admin/tax-compliance/mushak-9-1');
        $compileResponse->assertSessionHas('success');

        $this->assertDatabaseHas('mushak_9_1_returns', [
            'tax_period' => $taxPeriod,
            'status' => 'finalized',
        ]);
    }

    public function test_admin_can_submit_mushak_9_1_return_with_treasury_details(): void
    {
        $taxPeriod = CarbonImmutable::now()->format('Y-m');
        $compileAction = app(GenerateMushak91ReturnAction::class);
        $vatReturn = $compileAction($taxPeriod, ['force' => true]);

        $submitResponse = $this->actingAs($this->admin)
            ->from('/admin/tax-compliance/mushak-9-1')
            ->post("/admin/tax-compliance/mushak-9-1/{$vatReturn->id}/submit", [
                'treasury_challan_number' => 'TR-NBR-202610-0991',
                'treasury_challan_date' => '2026-10-28',
                'treasury_deposit_amount_bdt' => '4500.00',
            ]);

        $submitResponse->assertRedirect('/admin/tax-compliance/mushak-9-1');
        $submitResponse->assertSessionHas('success');

        $this->assertDatabaseHas('mushak_9_1_returns', [
            'id' => $vatReturn->id,
            'status' => 'submitted',
            'treasury_challan_number' => 'TR-NBR-202610-0991',
        ]);
    }

    public function test_admin_can_view_mushak_6_6_vds_certificates(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-TEST-01',
            'name' => 'Domestic Components Ltd.',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001928374-0101',
            'address' => 'Mirpur, Dhaka',
            'is_active' => true,
        ]);

        $po = LocalPurchaseOrder::create([
            'po_number' => 'PO-LOC-202610-888',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-15',
            'status' => 'received',
            'mushak_6_3_invoice_number' => 'M6.3-DOM-99881',
            'mushak_6_3_invoice_date' => '2026-10-15',
            'subtotal_bdt' => '50000.0000',
            'vat_amount_bdt' => '7500.0000',
            'vds_rate_percent' => '5.00',
            'vds_amount_bdt' => '2500.0000',
            'tds_rate_percent' => '3.00',
            'tds_amount_bdt' => '1500.0000',
            'local_freight_bdt' => '500.0000',
            'total_landed_cost_bdt' => '50500.0000',
            'net_payable_to_vendor_bdt' => '53500.0000',
        ]);

        $vdsAction = app(GenerateMushak66CertificateAction::class);
        $vdsResult = $vdsAction($po);

        $response = $this->actingAs($this->admin)->get('/admin/tax-compliance/mushak-6-6');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/Mushak66Index')
                ->has('certificates.data', 1)
                ->where('certificates.data.0.certificate_number', $vdsResult->certificate->certificate_number)
        );
    }

    public function test_admin_can_view_customer_returns_rma_and_filter_status(): void
    {
        ReturnMerchandiseAuthorization::create([
            'rma_number' => 'RMA-202610-999',
            'order_id' => $this->order->id,
            'status' => 'requested',
            'reason_category' => 'defective',
            'customer_notes' => 'Compressor noise reported.',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/tax-compliance/rma?status=requested');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/RmaIndex')
                ->has('rmas.data', 1)
                ->where('rmas.data.0.rma_number', 'RMA-202610-999')
        );
    }

    public function test_admin_can_view_nbr_statutory_registers_6_1_and_6_2(): void
    {
        $taxPeriod = CarbonImmutable::now()->format('Y-m');

        $response = $this->actingAs($this->admin)->get("/admin/tax-compliance/registers?tax_period={$taxPeriod}");

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/TaxCompliance/MushakRegisters')
                ->where('taxPeriod', $taxPeriod)
                ->has('register61')
                ->has('register62')
        );
    }
}
