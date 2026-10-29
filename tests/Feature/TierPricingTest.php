<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Actions\CalculateApplicablePriceAction;
use App\Modules\Pricing\Models\PricingTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierPricingTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Mobile Accessories', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Baseus Global', 'origin_country' => 'China', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => '65W GaN Fast Charger',
            'hs_code' => '8504.40.90',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'BASEUS-65W-BLK',
            'attributes' => ['Color' => 'Black'],
            'purchase_cost_bdt' => 1800.00,
            'selling_price_bdt' => 2500.00,
            'weight_kg' => 0.200,
            'is_active' => true,
        ]);

        // Create Retail Volume Tier (5+ units -> 2400 BDT)
        PricingTier::create([
            'product_variant_id' => $this->variant->id,
            'customer_group' => PricingTier::GROUP_RETAIL,
            'min_quantity' => 5,
            'tier_price_bdt' => 2400.00,
            'is_active' => true,
        ]);

        // Create Wholesale B2B Volume Tiers (10+ units -> 2100 BDT, 50+ units -> 1950 BDT)
        PricingTier::create([
            'product_variant_id' => $this->variant->id,
            'customer_group' => PricingTier::GROUP_WHOLESALE,
            'min_quantity' => 10,
            'tier_price_bdt' => 2100.00,
            'is_active' => true,
        ]);

        PricingTier::create([
            'product_variant_id' => $this->variant->id,
            'customer_group' => PricingTier::GROUP_WHOLESALE,
            'min_quantity' => 50,
            'tier_price_bdt' => 1950.00,
            'is_active' => true,
        ]);
    }

    public function test_standard_retail_price_for_single_item(): void
    {
        $action = new CalculateApplicablePriceAction;
        $result = $action($this->variant, 1, PricingTier::GROUP_RETAIL);

        $this->assertEquals(2500.00, $result['unit_price_bdt']);
        $this->assertEquals(2500.00, $result['total_price_bdt']);
        $this->assertFalse($result['is_tier_applied']);
        $this->assertEquals(0.0, $result['total_savings_bdt']);
    }

    public function test_retail_volume_discount_tier_applied(): void
    {
        $action = new CalculateApplicablePriceAction;
        $result = $action($this->variant, 6, PricingTier::GROUP_RETAIL);

        // 6 units @ 2400 BDT
        $this->assertEquals(2400.00, $result['unit_price_bdt']);
        $this->assertEquals(14400.00, $result['total_price_bdt']);
        $this->assertTrue($result['is_tier_applied']);
        $this->assertEquals(100.00, $result['savings_per_unit_bdt']);
        $this->assertEquals(600.00, $result['total_savings_bdt']);
    }

    public function test_wholesale_b2b_tiers_and_volume_breaks(): void
    {
        $action = new CalculateApplicablePriceAction;

        // Tier 1: 15 units (>= 10 MOQ) -> 2100 BDT/unit
        $resultTier1 = $action($this->variant, 15, PricingTier::GROUP_WHOLESALE);
        $this->assertEquals(2100.00, $resultTier1['unit_price_bdt']);
        $this->assertEquals(31500.00, $resultTier1['total_price_bdt']);
        $this->assertTrue($resultTier1['is_tier_applied']);
        $this->assertEquals(400.00, $resultTier1['savings_per_unit_bdt']);
        $this->assertEquals(6000.00, $resultTier1['total_savings_bdt']);

        // Tier 2: 100 units (>= 50 MOQ) -> 1950 BDT/unit
        $resultTier2 = $action($this->variant, 100, PricingTier::GROUP_WHOLESALE);
        $this->assertEquals(1950.00, $resultTier2['unit_price_bdt']);
        $this->assertEquals(195000.00, $resultTier2['total_price_bdt']);
        $this->assertEquals(550.00, $resultTier2['savings_per_unit_bdt']);
        $this->assertEquals(55000.00, $resultTier2['total_savings_bdt']);
    }

    public function test_b2b_below_moq_falls_back_to_retail_price(): void
    {
        $action = new CalculateApplicablePriceAction;
        // B2B buyer ordering only 2 units (below 10 MOQ)
        $result = $action($this->variant, 2, PricingTier::GROUP_WHOLESALE);

        $this->assertEquals(2500.00, $result['unit_price_bdt']);
        $this->assertEquals(5000.00, $result['total_price_bdt']);
        $this->assertFalse($result['is_tier_applied']);
    }
}
