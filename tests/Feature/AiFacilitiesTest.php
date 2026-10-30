<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AI\Actions\DetectTaxAnomaliesAction;
use App\Modules\AI\Actions\DraftCustomerSupportReplyAction;
use App\Modules\AI\Actions\EnrichProductSeoAction;
use App\Modules\AI\Actions\GenerateRestockInsightsAction;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\Mushak67CreditNote;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AiFacilitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'facilities_admin@example.com',
        ]);

        AiModelSetting::create([
            'provider' => AiModelSetting::PROVIDER_GEMINI,
            'name' => 'Google Gemini Flash',
            'is_active' => true,
            'default_model' => 'gemini-1.5-flash',
            'assigned_features' => ['*'],
            'credentials' => [
                'api_key' => 'valid_mock_gemini_key_8899',
            ],
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
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'origin_country' => 'South Korea',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'slug' => 'samsung-galaxy-s24-ultra',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price_bdt' => '145000.00',
            'hs_code' => '8517.13.00',
            'origin_country' => 'Vietnam',
            'is_active' => true,
            'specifications' => [
                'Processor' => 'Snapdragon 8 Gen 3',
                'RAM' => '12GB',
                'Camera' => '200MP Quad Camera',
            ],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SM-S24U-512-TI',
            'attributes' => ['Color' => 'Titanium Gray', 'Storage' => '512GB'],
            'selling_price_bdt' => '145000.00',
            'purchase_cost_bdt' => '120000.00',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-202610-AI01',
            'customer_name' => 'Tanvir Ahmed',
            'customer_phone' => '01712345678',
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'Banani Road 11, Dhaka',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'subtotal_bdt' => '145000.0000',
            'discount_amount_bdt' => '0.0000',
            'shipping_fee_bdt' => '0.0000',
            'total_bdt' => '145000.0000',
            'advance_paid_bdt' => '0.0000',
            'payable_cod_amount_bdt' => '145000.0000',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cash_on_delivery',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => (string) $this->product->id,
            'product_variant_id' => (string) $variant->id,
            'product_name' => $this->product->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price_bdt' => '145000.0000',
            'tier_savings_bdt' => '0.0000',
            'subtotal_bdt' => '145000.0000',
            'unit_cogs_bdt' => '120000.0000',
        ]);
    }

    public function test_enrich_product_seo_action_generates_and_persists_seo_metadata(): void
    {
        $mockPayload = json_encode([
            'seo_title' => 'Samsung Galaxy S24 Ultra Price in Bangladesh | Official Warranty',
            'meta_description' => 'Buy official Samsung Galaxy S24 Ultra with Snapdragon 8 Gen 3 in Bangladesh. Best price, 0% EMI and official warranty with instant home delivery.',
            'keywords' => ['samsung s24 ultra bd', 's24 ultra price bangladesh', 'snapdragon 8 gen 3 phone'],
            'marketing_bullets' => [
                '200MP Pro-Grade Camera with 100x Space Zoom',
                'Titanium frame for ultimate enterprise durability',
                'Long-lasting 5000mAh battery with 45W fast charge',
            ],
            'short_description' => 'Experience the peak of smartphone performance with Galaxy AI and titanium design.',
            'suggested_tags' => ['samsung', 'flagship', 'galaxy-ai'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "```json\n{$mockPayload}\n```"],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 350,
                    'candidatesTokenCount' => 120,
                ],
            ], 200),
        ]);

        $action = app(EnrichProductSeoAction::class);
        $result = $action($this->product, ['persist' => true, 'user_id' => $this->admin->id]);

        $this->assertSame('Samsung Galaxy S24 Ultra Price in Bangladesh | Official Warranty', $result->seoTitle);
        $this->assertStringContainsString('Snapdragon 8 Gen 3', $result->metaDescription);
        $this->assertCount(3, $result->keywords);
        $this->assertCount(3, $result->marketingBullets);
        $this->assertCount(3, $result->suggestedTags);

        // Verify persisted into products table
        $this->product->refresh();
        $this->assertSame($result->seoTitle, $this->product->seo_title);
        $this->assertSame($result->metaDescription, $this->product->seo_description);

        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => AiModelSetting::FEATURE_CATALOG_ENRICHMENT,
            'status' => 'success',
        ]);
    }

    public function test_generate_restock_insights_action_computes_reorder_target_and_rationale(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Critical stock depletion detected with only 3 days of buffer remaining. Urgent PO issuance recommended to overseas manufacturer to avoid stockout.'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 180,
                    'candidatesTokenCount' => 35,
                ],
            ], 200),
        ]);

        $action = app(GenerateRestockInsightsAction::class);

        // 30 days sold = 90 (velocity = 3.0/day), stock = 9 (days remaining = 3), lead = 14, safety = 7 (buffer = 21 * 3 = 63, suggested = 63 - 9 = 54)
        $insight = $action('SM-S24U-512-TI', [
            'product_name' => 'Samsung Galaxy S24 Ultra 512GB',
            'current_stock' => 9,
            'units_sold_30_days' => 90,
            'lead_time_days' => 14,
            'safety_stock_days' => 7,
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame('SM-S24U-512-TI', $insight->sku);
        $this->assertSame(9, $insight->currentStock);
        $this->assertEquals(3.0, $insight->dailyVelocity);
        $this->assertSame(3, $insight->daysRemaining);
        $this->assertSame('critical', $insight->urgencyLevel);
        $this->assertSame(54, $insight->suggestedReorderQuantity);
        $this->assertStringContainsString('Critical stock depletion', $insight->aiRationale);
    }

    public function test_draft_customer_support_reply_action_generates_contextual_response(): void
    {
        $rma = ReturnMerchandiseAuthorization::create([
            'rma_number' => 'RMA-202610-888',
            'order_id' => $this->order->id,
            'status' => 'inspected',
            'reason_category' => 'defective',
            'customer_notes' => 'Display screen flickering intermittently on brightness change.',
            'inspection_outcome' => 'restock_available',
            'refund_amount_bdt' => '145000.0000',
            'created_at' => now(),
        ]);

        $mockReply = "Subject: Return Request RMA-202610-888 Approved - ElectroCommerce BD\nAction: Process bKash/Card refund of 145,000 BDT\nMessage:\nDear Tanvir Ahmed,\nWe have completed inspection of your Samsung Galaxy S24 Ultra and approved your warranty claim. Your full refund of ৳145,000 will be credited within 3-5 business days.";

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $mockReply],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 220,
                    'candidatesTokenCount' => 75,
                ],
            ], 200),
        ]);

        $action = app(DraftCustomerSupportReplyAction::class);
        $draft = $action($rma, ['user_id' => $this->admin->id]);

        $this->assertStringContainsString('RMA-202610-888 Approved', $draft->subject);
        $this->assertStringContainsString('Tanvir Ahmed', $draft->body);
        $this->assertStringContainsString('145,000', $draft->body);
        $this->assertTrue($draft->requiresHumanReview);
    }

    public function test_detect_tax_anomalies_action_flags_high_credit_note_variance(): void
    {
        $period = CarbonImmutable::now()->format('Y-m');

        // Create outward invoice with 10,000 VAT
        $invoice = Mushak63Invoice::create([
            'invoice_number' => 'M6.3-202610-9901',
            'tax_period' => $period,
            'issue_date' => now(),
            'order_id' => $this->order->id,
            'customer_name' => 'Tanvir Ahmed',
            'customer_phone' => '01712345678',
            'customer_address' => 'Banani, Dhaka',
            'destination_address' => 'Dhaka',
            'seller_name' => 'ElectroCommerce BD',
            'seller_bin' => '001234567-0101',
            'seller_address' => 'Dhaka',
            'subtotal_exclusive_bdt' => '100000.0000',
            'supplementary_duty_total_bdt' => '0.0000',
            'vat_total_bdt' => '10000.0000',
            'grand_total_bdt' => '110000.0000',
            'integrity_hash' => 'dummy_hash',
            'status' => 'issued',
        ]);

        // Create credit note with 3,000 VAT reduction (30% ratio! > 10% threshold)
        Mushak67CreditNote::create([
            'credit_note_number' => 'M6.7-202610-0001',
            'tax_period' => $period,
            'issue_date' => now(),
            'order_id' => $this->order->id,
            'mushak_6_3_invoice_id' => $invoice->id,
            'mushak_6_3_invoice_number' => $invoice->invoice_number,
            'mushak_6_3_invoice_date' => now(),
            'seller_name' => 'ElectroCommerce BD',
            'seller_bin' => '001234567-0101',
            'seller_address' => 'Dhaka',
            'customer_name' => 'Tanvir Ahmed',
            'customer_phone' => '01712345678',
            'customer_address' => 'Banani, Dhaka',
            'reason_for_return' => 'Defective AC unit',
            'original_invoice_total_bdt' => '110000.0000',
            'returned_value_exclusive_bdt' => '20000.0000',
            'returned_sd_amount_bdt' => '0.0000',
            'returned_vat_amount_bdt' => '3000.0000',
            'total_credit_amount_bdt' => '23000.0000',
            'integrity_hash' => 'dummy_hash',
            'status' => 'issued',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'High audit risk: Credit note decreasing adjustment ratio of 30% significantly exceeds normal retail threshold of 5-8%.'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 160,
                    'candidatesTokenCount' => 40,
                ],
            ], 200),
        ]);

        $action = app(DetectTaxAnomaliesAction::class);
        $report = $action($period, ['user_id' => $this->admin->id]);

        $this->assertSame($period, $report->taxPeriod);
        $this->assertTrue($report->anomalyDetected);
        $this->assertEquals(30.0, $report->creditNoteRatioPercent);
        $this->assertNotEmpty($report->issues);
        $this->assertStringContainsString('High sales return ratio', $report->issues[0]);
        $this->assertStringContainsString('High audit risk', $report->aiSummary);
    }
}
