<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Procurement\Models\ImportPoItem;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOrderTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'code' => 'SUP-INT-001',
            'name' => 'Shenzhen Tech Innovators Co., Ltd',
            'type' => 'international',
            'country_code' => 'CN',
            'currency' => 'USD',
            'bank_details' => [
                'bank_name' => 'Bank of China',
                'swift_bic' => 'BKCHCNBJ',
                'account_number' => '123456789',
            ],
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

        $this->product = Product::create([
            'name' => 'Noise Cancelling Wireless Headphones',
            'slug' => 'noise-cancelling-wireless-headphones',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'hs_code' => '8518.30.00',
            'warranty_policy' => '1 Year Official Warranty',
            'origin_country' => 'China',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'NCWH-BLK',
            'barcode_ean' => '8941234567890',
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '3675.0000',
            'selling_price_bdt' => '5500.0000',
            'is_active' => true,
        ]);
    }

    public function test_can_create_import_purchase_order_with_lc_and_fx_settlement_rates(): void
    {
        $order = ImportPurchaseOrder::create([
            'po_number' => 'IPO-2026-0001',
            'supplier_id' => $this->supplier->id,
            'status' => 'lc_opened',
            'proforma_invoice_number' => 'PI-SZ-2026-99',
            'proforma_invoice_date' => '2026-10-01',
            'commercial_invoice_number' => 'CI-SZ-2026-99',
            'commercial_invoice_date' => '2026-10-15',
            'payment_method' => 'letter_of_credit',
            'lc_number' => 'LC-EBL-2026-4491',
            'lc_issuing_bank' => 'Eastern Bank PLC',
            'lc_issue_date' => '2026-10-05',
            'lc_margin_percentage' => '15.00',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => '122.5000',
            'port_of_loading' => 'Shenzhen Port, China',
            'port_of_discharge' => 'Chattogram',
            'bill_of_lading_number' => 'COSU632190881',
            'vessel_or_flight_name' => 'COSCO Shipping Venus',
            'estimated_arrival_date' => '2026-11-10',
            'notes' => 'Import consignment containing Q4 electronics inventory.',
        ]);

        $this->assertDatabaseHas('import_purchase_orders', [
            'id' => $order->id,
            'po_number' => 'IPO-2026-0001',
            'lc_number' => 'LC-EBL-2026-4491',
            'currency' => 'USD',
            'port_of_discharge' => 'Chattogram',
        ]);

        $this->assertNotEmpty($order->ulid);
        $this->assertEquals('Eastern Bank PLC', $order->lc_issuing_bank);
        $this->assertEquals('122.5000', (string) $order->exchange_rate_to_bdt);
    }

    public function test_can_add_items_to_import_po_and_recalculate_totals_in_bdt(): void
    {
        $exchangeRate = '122.5000';

        $order = ImportPurchaseOrder::create([
            'po_number' => 'IPO-2026-0002',
            'supplier_id' => $this->supplier->id,
            'status' => 'in_transit',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => $exchangeRate,
            'port_of_discharge' => 'Chattogram',
        ]);

        // Item 1: 100 units at $30.00 USD each = $3,000.00 USD = ৳367,500.00 BDT
        $unitPriceForeign1 = '30.0000';
        $unitPriceBdt1 = bcmul($unitPriceForeign1, $exchangeRate, 4); // 3675.0000
        $qty1 = 100;
        $totalForeign1 = bcmul($unitPriceForeign1, (string) $qty1, 4); // 3000.0000
        $totalBdt1 = bcmul($unitPriceBdt1, (string) $qty1, 4); // 367500.0000

        ImportPoItem::create([
            'import_purchase_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'hs_code' => $this->product->hs_code,
            'quantity_ordered' => $qty1,
            'quantity_received' => 0,
            'unit_price_foreign' => $unitPriceForeign1,
            'unit_price_bdt' => $unitPriceBdt1,
            'total_fob_foreign' => $totalForeign1,
            'total_fob_bdt' => $totalBdt1,
        ]);

        // Item 2: 50 units at $50.00 USD each = $2,500.00 USD = ৳306,250.00 BDT
        $unitPriceForeign2 = '50.0000';
        $unitPriceBdt2 = bcmul($unitPriceForeign2, $exchangeRate, 4); // 6125.0000
        $qty2 = 50;
        $totalForeign2 = bcmul($unitPriceForeign2, (string) $qty2, 4); // 2500.0000
        $totalBdt2 = bcmul($unitPriceBdt2, (string) $qty2, 4); // 306250.0000

        ImportPoItem::create([
            'import_purchase_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'hs_code' => $this->product->hs_code,
            'quantity_ordered' => $qty2,
            'quantity_received' => 0,
            'unit_price_foreign' => $unitPriceForeign2,
            'unit_price_bdt' => $unitPriceBdt2,
            'total_fob_foreign' => $totalForeign2,
            'total_fob_bdt' => $totalBdt2,
        ]);

        $order->load('items');
        $order->recalculateTotals();

        $this->assertEquals(2, $order->items->count());
        $this->assertEquals('5500.0000', (string) $order->fresh()->total_fob_foreign);
        $this->assertEquals('673750.0000', (string) $order->fresh()->total_fob_bdt);
        $this->assertEquals('in_transit', $order->status);
        $this->assertEquals(1, ImportPurchaseOrder::inTransit()->count());
    }
}
