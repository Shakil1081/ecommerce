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
use App\Modules\TaxCompliance\Actions\GenerateMushak63InvoiceAction;
use App\Modules\TaxCompliance\Actions\VerifyMushak63IntegrityAction;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Mushak63InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Product $product;

    private ProductVariant $variant;

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
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Sony',
            'slug' => 'sony',
            'origin_country' => 'Japan',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Sony ANC Headphones',
            'slug' => 'sony-anc-headphones',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '11500.00',
            'hs_code' => '8518.30.00',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SONY-WH-BLK',
            'attributes' => ['Color' => 'Black'],
            'selling_price_bdt' => '11500.00',
            'purchase_cost_bdt' => '8000.00',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-202610-99901',
            'customer_name' => 'Rahim Uddin',
            'customer_phone' => '01712345678',
            'customer_email' => 'rahim@example.com',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            'subtotal_bdt' => '11500.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '60.0000',
            'total_bdt' => '11560.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '11560.0000',
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
            'unit_price_bdt' => '11500.0000',
            'tier_savings_bdt' => '0.0000',
            'subtotal_bdt' => '11500.0000',
            'unit_cogs_bdt' => '8000.0000',
        ]);
    }

    public function test_mushak_sequence_service_generates_sequential_numbers_without_gaps(): void
    {
        $service = app(MushakSequenceService::class);
        $date = CarbonImmutable::parse('2026-10-30');

        $seq1 = $service->generateNextSequence('6.3', $date);
        $seq2 = $service->generateNextSequence('6.3', $date);
        $seq3 = $service->generateNextSequence('6.3', $date);

        $this->assertSame('M6.3-202610-000001', $seq1);
        $this->assertSame('M6.3-202610-000002', $seq2);
        $this->assertSame('M6.3-202610-000003', $seq3);

        // Different document type maintains independent sequence
        $seqMushak65 = $service->generateNextSequence('6.5', $date);
        $this->assertSame('M6.5-202610-000001', $seqMushak65);
    }

    public function test_generates_statutory_mushak_6_3_invoice_with_breakdown_and_hash(): void
    {
        $action = app(GenerateMushak63InvoiceAction::class);

        $result = $action($this->order, [
            'seller_name' => 'ElectroCommerce BD Ltd.',
            'seller_bin' => '001234567-0101',
            'vat_rate_percent' => 15.00,
            'prices_include_vat' => true,
        ]);

        $this->assertFalse($result->isExisting);
        $this->assertStringStartsWith('M6.3-', $result->invoiceNumber);

        $invoice = $result->invoice;
        $this->assertInstanceOf(Mushak63Invoice::class, $invoice);
        $this->assertSame($this->order->id, $invoice->order_id);
        $this->assertSame('001234567-0101', $invoice->seller_bin);
        $this->assertSame('Rahim Uddin', $invoice->customer_name);
        $this->assertSame('issued', $invoice->status);
        $this->assertNotEmpty($invoice->integrity_hash);

        // 11500 inclusive of 15% VAT: exclusive = 10000, VAT = 1500
        $this->assertEquals(10000.00, (float) $invoice->subtotal_exclusive_bdt);
        $this->assertEquals(1500.00, (float) $invoice->vat_total_bdt);
        $this->assertEquals(11560.00, (float) $invoice->grand_total_bdt);

        $this->assertCount(1, $invoice->items);
        $item = $invoice->items->first();
        $this->assertSame('Sony ANC Headphones', $item->item_name);
        $this->assertSame('8518.30.00', $item->hs_code);
        $this->assertEquals(10000.00, (float) $item->subtotal_exclusive_bdt);
        $this->assertEquals(1500.00, (float) $item->vat_amount_bdt);
        $this->assertEquals(15.00, (float) $item->vat_rate_percent);

        // Order reference and relation
        $this->order->refresh();
        $this->assertSame($result->invoiceNumber, $this->order->mushak_6_3_number);
        $this->assertNotNull($this->order->mushak63Invoice);
        $this->assertSame($invoice->id, $this->order->mushak63Invoice->id);
    }

    public function test_generation_is_idempotent_for_the_same_order(): void
    {
        $action = app(GenerateMushak63InvoiceAction::class);

        $result1 = $action($this->order);
        $result2 = $action($this->order);

        $this->assertFalse($result1->isExisting);
        $this->assertTrue($result2->isExisting);
        $this->assertSame($result1->invoiceNumber, $result2->invoiceNumber);
        $this->assertSame($result1->invoice->id, $result2->invoice->id);
        $this->assertSame(1, Mushak63Invoice::where('order_id', $this->order->id)->count());
    }

    public function test_supports_retail_trader_rate_and_exempt_items(): void
    {
        $action = app(GenerateMushak63InvoiceAction::class);

        // Retail trader rate 5%
        $orderItem = $this->order->items->first();
        $result = $action($this->order, [
            'vat_rate_percent' => 5.00,
            'prices_include_vat' => true,
        ]);

        $invoice = $result->invoice;
        // 11500 / 1.05 = 10952.3810, VAT = 547.6190
        $this->assertEqualsWithDelta(10952.38, (float) $invoice->subtotal_exclusive_bdt, 0.05);
        $this->assertEqualsWithDelta(547.62, (float) $invoice->vat_total_bdt, 0.05);
    }

    public function test_verify_mushak_6_3_integrity_action(): void
    {
        $action = app(GenerateMushak63InvoiceAction::class);
        $verifier = app(VerifyMushak63IntegrityAction::class);

        $result = $action($this->order);
        $invoice = $result->invoice;

        // Fresh invoice passes verification
        $this->assertTrue($verifier($invoice));

        // Tampering with values fails verification
        $invoice->vat_total_bdt = '9999.0000';
        $invoice->saveQuietly();

        $this->assertFalse($verifier($invoice));
    }
}
