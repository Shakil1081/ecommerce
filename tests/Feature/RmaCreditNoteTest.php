<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Inventory\Actions\AdjustInventoryStockAction;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\TaxCompliance\Actions\CreateRmaRequestAction;
use App\Modules\TaxCompliance\Actions\GenerateMushak63InvoiceAction;
use App\Modules\TaxCompliance\Actions\IssueMushak67CreditNoteAction;
use App\Modules\TaxCompliance\Actions\ProcessRmaInspectionAction;
use App\Modules\TaxCompliance\Models\Mushak67CreditNote;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class RmaCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Product $product;

    private ProductVariant $variant;

    private Order $order;

    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create([
            'name' => 'Dhaka Central Hub',
            'code' => 'CENTRAL-DHA',
            'address' => 'Plot 10, Tejgaon I/A, Dhaka-1208',
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
            'code' => 'DHA-D',
            'zone_type' => 'inside_dhaka',
        ]);

        $category = Category::create([
            'name' => 'Audio',
            'slug' => 'audio',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Bose',
            'slug' => 'bose',
            'origin_country' => 'USA',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Bose QuietComfort 45',
            'slug' => 'bose-quietcomfort-45',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '34500.00',
            'hs_code' => '8518.30.00',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'BOSE-QC45-BLK',
            'attributes' => ['Color' => 'Triple Black'],
            'selling_price_bdt' => '34500.00',
            'purchase_cost_bdt' => '25000.00',
            'is_active' => true,
        ]);

        // Initial inventory: 10 units in warehouse
        app(AdjustInventoryStockAction::class)(
            $this->warehouse,
            $this->variant,
            10,
            'initial_seed'
        );

        $this->order = Order::create([
            'order_number' => 'ORD-202610-RMA01',
            'customer_name' => 'Kazi Nazrul',
            'customer_phone' => '01711223344',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Gulshan 2, Dhaka',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'subtotal_bdt' => '69000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '0.0000',
            'total_bdt' => '69000.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '69000.0000',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cash_on_delivery',
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => (string) $this->product->id,
            'product_variant_id' => (string) $this->variant->id,
            'product_name' => $this->product->name,
            'sku' => $this->variant->sku,
            'quantity' => 2,
            'unit_price_bdt' => '34500.0000',
            'tier_savings_bdt' => '0.0000',
            'subtotal_bdt' => '69000.0000',
            'unit_cogs_bdt' => '25000.0000',
        ]);

        // Generate statutory Mushak 6.3 Invoice for this order
        // 69,000 inclusive of 15% VAT: exclusive = 60,000, VAT = 9,000
        app(GenerateMushak63InvoiceAction::class)($this->order, [
            'vat_rate_percent' => 15.00,
            'prices_include_vat' => true,
        ]);
    }

    public function test_create_rma_request_action(): void
    {
        $action = app(CreateRmaRequestAction::class);

        $rma = $action(
            $this->order,
            [
                [
                    'order_item_id' => $this->orderItem->id,
                    'quantity' => 1,
                    'condition_note' => 'Left ear cup audio balance distortion',
                ],
            ],
            'defective',
            'Audio issue out of box'
        );

        $this->assertInstanceOf(ReturnMerchandiseAuthorization::class, $rma);
        $this->assertSame('requested', $rma->status);
        $this->assertSame('defective', $rma->reason_category);
        $this->assertEquals(34500.00, (float) $rma->refund_amount_bdt);
        $this->assertCount(1, $rma->items);

        $rmaItem = $rma->items->first();
        $this->assertSame($this->orderItem->id, $rmaItem->order_item_id);
        $this->assertSame(1, $rmaItem->quantity_requested);
        $this->assertSame(1, $rmaItem->quantity_approved);
    }

    public function test_issue_mushak_6_7_credit_note_and_computes_decreasing_tax_adjustment(): void
    {
        $action = app(IssueMushak67CreditNoteAction::class);
        $issueDate = CarbonImmutable::parse('2026-10-26 15:00:00');

        $creditNote = $action(
            $this->order,
            [
                [
                    'order_item_id' => $this->orderItem->id,
                    'quantity' => 1, // return 1 of 2
                ],
            ],
            'Defective speaker hardware',
            null,
            ['issue_date' => $issueDate]
        );

        $this->assertInstanceOf(Mushak67CreditNote::class, $creditNote);
        $this->assertSame('M6.7-202610-000001', $creditNote->credit_note_number);
        $this->assertSame($this->order->mushak_6_3_number, $creditNote->mushak_6_3_invoice_number);

        // Original 2 units were: exclusive = 60,000, VAT = 9,000 (unit exclusive = 30,000, unit VAT = 4,500)
        // 1 returned unit: exclusive = 30,000, VAT = 4,500, Total Credit = 34,500
        $this->assertEquals(30000.00, (float) $creditNote->returned_value_exclusive_bdt);
        $this->assertEquals(4500.00, (float) $creditNote->returned_vat_amount_bdt); // Decreasing tax adjustment
        $this->assertEquals(34500.00, (float) $creditNote->total_credit_amount_bdt);
        $this->assertSame('issued', $creditNote->status);
        $this->assertNotEmpty($creditNote->integrity_hash);

        $this->assertCount(1, $creditNote->items);
        $lineItem = $creditNote->items->first();
        $this->assertSame(1, $lineItem->quantity_returned);
        $this->assertEquals(30000.00, (float) $lineItem->subtotal_exclusive_bdt);
        $this->assertEquals(4500.00, (float) $lineItem->vat_amount_bdt);
    }

    public function test_process_rma_inspection_with_restock_reverses_inventory_and_issues_credit_note(): void
    {
        $createRma = app(CreateRmaRequestAction::class);
        $processRma = app(ProcessRmaInspectionAction::class);

        $rma = $createRma(
            $this->order,
            [['order_item_id' => $this->orderItem->id, 'quantity' => 1]],
            'wrong_item'
        );

        $stockBefore = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first()->physical_quantity;

        $completedRma = $processRma(
            $rma,
            'restock_available',
            $this->warehouse,
            'Item in brand-new sealed condition, restocked to shelf',
            [
                'refund_method' => 'bkash',
                'refund_transaction_reference' => 'BKASH-REF-88912',
            ]
        );

        $this->assertSame('completed', $completedRma->status);
        $this->assertSame('restock_available', $completedRma->inspection_outcome);
        $this->assertSame('processed', $completedRma->refund_status);
        $this->assertSame('BKASH-REF-88912', $completedRma->refund_transaction_reference);

        // Stock reversed +1 unit
        $stockAfter = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first()->physical_quantity;

        $this->assertSame($stockBefore + 1, $stockAfter);

        // Linked Mushak 6.7 Credit Note automatically created
        $this->assertNotNull($completedRma->creditNote);
        $this->assertSame('issued', $completedRma->creditNote->status);
        $this->assertStringStartsWith('M6.7-', $completedRma->creditNote->credit_note_number);
    }

    public function test_process_rma_inspection_with_quarantine_does_not_increment_available_stock(): void
    {
        $createRma = app(CreateRmaRequestAction::class);
        $processRma = app(ProcessRmaInspectionAction::class);

        $rma = $createRma(
            $this->order,
            [['order_item_id' => $this->orderItem->id, 'quantity' => 1]],
            'defective'
        );

        $stockBefore = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first()->physical_quantity;

        $completedRma = $processRma(
            $rma,
            'quarantine_damaged',
            $this->warehouse,
            'Physical crack detected. Sent to quarantine scrap bin.'
        );

        $this->assertSame('completed', $completedRma->status);
        $this->assertSame('quarantine_damaged', $completedRma->inspection_outcome);

        // Stock NOT incremented because item is damaged/quarantined
        $stockAfter = InventoryStock::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first()->physical_quantity;

        $this->assertSame($stockBefore, $stockAfter);
        $this->assertNotNull($completedRma->creditNote);
    }

    public function test_prevents_returning_more_than_invoiced(): void
    {
        $action = app(IssueMushak67CreditNoteAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds invoiced quantity');

        $action(
            $this->order,
            [['order_item_id' => $this->orderItem->id, 'quantity' => 10]], // ordered only 2
            'Attempt over-return'
        );
    }
}
