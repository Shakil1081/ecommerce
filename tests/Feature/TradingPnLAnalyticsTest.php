<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Admin\Actions\CalculateTradingPnLAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TradingPnLAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $categoryPhones;

    private Category $categoryLaptops;

    private Brand $brandApple;

    private BangladeshDivision $division;

    private BangladeshDistrict $district;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'pnl_admin@example.com',
        ]);

        $this->division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA',
        ]);

        $this->district = BangladeshDistrict::create([
            'division_id' => $this->division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-D',
            'zone_type' => 'inside_dhaka',
        ]);

        $this->categoryPhones = Category::create([
            'name' => 'Mobile Phones',
            'slug' => 'mobile-phones',
            'is_active' => true,
        ]);

        $this->categoryLaptops = Category::create([
            'name' => 'Laptops',
            'slug' => 'laptops',
            'is_active' => true,
        ]);

        $this->brandApple = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'origin_country' => 'USA',
            'is_active' => true,
        ]);
    }

    public function test_calculate_trading_pnl_action_computes_turnover_cogs_and_gross_margins(): void
    {
        // 1. Create iPhone Product & Variant
        $phone = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'category_id' => $this->categoryPhones->id,
            'brand_id' => $this->brandApple->id,
            'base_price_bdt' => '180000.00',
            'hs_code' => '8517.13.00',
            'origin_country' => 'China',
            'is_active' => true,
        ]);

        $phoneVariant = ProductVariant::create([
            'product_id' => $phone->id,
            'sku' => 'IPH16PM-256-NAT',
            'attributes' => ['Color' => 'Natural Titanium', 'Storage' => '256GB'],
            'selling_price_bdt' => '180000.00',
            'purchase_cost_bdt' => '140000.00', // Landed COGS in BDT
            'is_active' => true,
        ]);

        // 2. Create MacBook Product & Variant
        $macbook = Product::create([
            'name' => 'MacBook Pro M3',
            'slug' => 'macbook-pro-m3',
            'category_id' => $this->categoryLaptops->id,
            'brand_id' => $this->brandApple->id,
            'base_price_bdt' => '250000.00',
            'hs_code' => '8471.30.00',
            'origin_country' => 'USA',
            'is_active' => true,
        ]);

        $macVariant = ProductVariant::create([
            'product_id' => $macbook->id,
            'sku' => 'MBP-M3-512-SG',
            'attributes' => ['Color' => 'Space Gray', 'Storage' => '512GB'],
            'selling_price_bdt' => '250000.00',
            'purchase_cost_bdt' => '190000.00', // Landed COGS in BDT
            'is_active' => true,
        ]);

        // 3. Create Order 1 (1 iPhone)
        $order1 = Order::create([
            'order_number' => 'ORD-PNL-01',
            'customer_name' => 'Farhan Kabir',
            'customer_phone' => '01711112233',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Gulshan 1, Dhaka',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'subtotal_bdt' => '180000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '100.0000',
            'total_bdt' => '180100.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '180100.0000',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cash_on_delivery',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => (string) $phone->id,
            'product_variant_id' => (string) $phoneVariant->id,
            'product_name' => $phone->name,
            'sku' => $phoneVariant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '180000.0000',
            'tier_savings_bdt' => '0.0000',
            'subtotal_bdt' => '180000.0000',
            'unit_cogs_bdt' => '140000.0000', // COGS = 140,000
        ]);

        // 4. Create Order 2 (1 MacBook, 10,000 discount)
        $order2 = Order::create([
            'order_number' => 'ORD-PNL-02',
            'customer_name' => 'Sarah Hasan',
            'customer_phone' => '01988887766',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Dhanmondi, Dhaka',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'subtotal_bdt' => '250000.0000',
            'discount_amount_bdt' => '10000.0000',
            'shipping_fee_bdt' => '100.0000',
            'total_bdt' => '240100.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '240100.0000',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'bkash',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => (string) $macbook->id,
            'product_variant_id' => (string) $macVariant->id,
            'product_name' => $macbook->name,
            'sku' => $macVariant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '250000.0000',
            'tier_savings_bdt' => '10000.0000',
            'subtotal_bdt' => '250000.0000',
            'unit_cogs_bdt' => '190000.0000', // COGS = 190,000
        ]);

        // 5. Courier consignments: collected 200 total, courier bill is 140 (70 x 2) -> shipping variance = +60
        CourierConsignment::create([
            'order_id' => $order1->id,
            'courier_code' => 'steadfast',
            'consignment_id' => 'CID-001',
            'tracking_code' => 'TRK-001',
            'status' => 'delivered',
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '180100.0000',
            'recipient_name' => 'Farhan Kabir',
            'recipient_phone' => '01711112233',
            'recipient_address' => 'Gulshan 1, Dhaka',
            'created_at' => now(),
        ]);

        CourierConsignment::create([
            'order_id' => $order2->id,
            'courier_code' => 'steadfast',
            'consignment_id' => 'CID-002',
            'tracking_code' => 'TRK-002',
            'status' => 'in_transit',
            'delivery_zone' => 'inside_dhaka',
            'delivery_fee_bdt' => '70.0000',
            'cod_amount_bdt' => '0.0000',
            'recipient_name' => 'Sarah Hasan',
            'recipient_phone' => '01988887766',
            'recipient_address' => 'Dhanmondi, Dhaka',
            'created_at' => now(),
        ]);

        // 6. Payment transaction gateway fee: bKash fee 1.5% on 240,100 = 3,601.50
        PaymentTransaction::create([
            'order_id' => $order2->id,
            'gateway_code' => 'bkash',
            'transaction_id' => 'TRX-BKASH-9988',
            'payment_purpose' => 'order_full',
            'amount_bdt' => '240100.0000',
            'gateway_fee_bdt' => '3601.5000',
            'currency' => 'BDT',
            'status' => 'successful',
            'customer_account' => '01988887766',
            'created_at' => now(),
        ]);

        // 7. Output VAT: Mushak 6.3 invoices
        Mushak63Invoice::create([
            'invoice_number' => 'M6.3-202610-8801',
            'tax_period' => CarbonImmutable::now()->format('Y-m'),
            'issue_date' => now(),
            'order_id' => $order1->id,
            'customer_name' => 'Farhan Kabir',
            'customer_phone' => '01711112233',
            'customer_address' => 'Gulshan 1, Dhaka',
            'destination_address' => 'Dhaka',
            'seller_name' => 'ElectroCommerce BD',
            'seller_bin' => '001234567-0101',
            'seller_address' => 'Dhaka',
            'subtotal_exclusive_bdt' => '156521.7391',
            'supplementary_duty_total_bdt' => '0.0000',
            'vat_total_bdt' => '23478.2609',
            'grand_total_bdt' => '180000.0000',
            'integrity_hash' => 'hash1',
            'status' => 'issued',
        ]);

        $action = app(CalculateTradingPnLAction::class);
        $pnl = $action(options: ['period' => 'this_month']);

        // Expected Financial Math:
        // Gross Turnover = 180,000 + 250,000 = 430,000
        // Total Discounts = 10,000
        // Net Sales Revenue = 420,000
        // Total COGS = 140,000 (iPhone) + 190,000 (MacBook) = 330,000
        // Gross Profit = 420,000 - 330,000 = 90,000
        // Gross Margin % = (90,000 / 420,000) * 100 = 21.43%
        $this->assertEquals(430000.00, (float) $pnl->grossSalesTurnoverBdt);
        $this->assertEquals(10000.00, (float) $pnl->totalDiscountsBdt);
        $this->assertEquals(420000.00, (float) $pnl->netSalesRevenueBdt);
        $this->assertEquals(330000.00, (float) $pnl->totalCogsBdt);
        $this->assertEquals(90000.00, (float) $pnl->grossTradingProfitBdt);
        $this->assertEquals(21.43, $pnl->grossMarginPercent);

        // Logistics Economics:
        // Shipping collected = 100 + 100 = 200
        // Courier cost = 70 + 70 = 140
        // Shipping Variance = +60
        $this->assertEquals(200.00, (float) $pnl->shippingFeesCollectedBdt);
        $this->assertEquals(140.00, (float) $pnl->courierCostBdt);
        $this->assertEquals(60.00, (float) $pnl->shippingVarianceBdt);

        // Operating Profit:
        // Gross Profit (90,000) + Shipping Variance (60) - Gateway Fees (3,601.50) - Return Losses (0) = 86,458.50
        $this->assertEquals(86458.50, (float) $pnl->netOperatingProfitBdt);
        $this->assertEquals(20.59, $pnl->netMarginPercent);

        // Order KPIs:
        $this->assertSame(2, $pnl->totalOrdersCount);
        $this->assertSame(1, $pnl->deliveredOrdersCount);
        $this->assertSame(0, $pnl->cancelledOrdersCount);

        // Category Breakdown:
        $this->assertCount(2, $pnl->categoryBreakdown);
        $laptopsCategory = collect($pnl->categoryBreakdown)->firstWhere('category_name', 'Laptops');
        $this->assertNotNull($laptopsCategory);
        $this->assertEquals(250000.00, (float) $laptopsCategory['revenue_bdt']);
        $this->assertEquals(190000.00, (float) $laptopsCategory['cogs_bdt']);
        $this->assertEquals(60000.00, (float) $laptopsCategory['gross_profit_bdt']);

        // Top Performing SKUs:
        $this->assertCount(2, $pnl->topPerformingSkus);
        $topSku = $pnl->topPerformingSkus[0];
        $this->assertSame('MBP-M3-512-SG', $topSku['sku']);
        $this->assertEquals(60000.00, (float) $topSku['gross_profit_bdt']);
    }

    public function test_calculate_trading_pnl_action_deducts_rma_scrap_losses(): void
    {
        $phone = Product::create([
            'name' => 'Demo Phone',
            'slug' => 'demo-phone',
            'category_id' => $this->categoryPhones->id,
            'brand_id' => $this->brandApple->id,
            'base_price_bdt' => '50000.00',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $phone->id,
            'sku' => 'DEMO-PH-01',
            'attributes' => ['Color' => 'Black'],
            'selling_price_bdt' => '50000.00',
            'purchase_cost_bdt' => '35000.00',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PNL-RMA',
            'customer_name' => 'Rahim Ali',
            'customer_phone' => '01812345678',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Mirpur, Dhaka',
            'division_id' => $this->division->id,
            'district_id' => $this->district->id,
            'subtotal_bdt' => '50000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '70.0000',
            'total_bdt' => '50070.0000',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cash_on_delivery',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => (string) $phone->id,
            'product_variant_id' => (string) $variant->id,
            'product_name' => $phone->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '50000.0000',
            'subtotal_bdt' => '50000.0000',
            'unit_cogs_bdt' => '35000.0000',
        ]);

        // RMA with inspection_outcome = 'scrap' (complete loss / write-off)
        ReturnMerchandiseAuthorization::create([
            'rma_number' => 'RMA-SCRAP-01',
            'order_id' => $order->id,
            'status' => 'refunded',
            'reason_category' => 'defective',
            'inspection_outcome' => 'scrap',
            'refund_amount_bdt' => '50000.0000',
            'created_at' => now(),
        ]);

        $action = app(CalculateTradingPnLAction::class);
        $pnl = $action(options: ['period' => 'this_month']);

        $this->assertEquals(50000.00, (float) $pnl->returnLossesBdt);
        // Gross profit = 15,000 (50k - 35k), Return losses = 50,000 => Net operating profit is negative
        $this->assertTrue((float) $pnl->netOperatingProfitBdt < 0);
    }
}
