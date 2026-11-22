<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Support\Str;

/**
 * Minimal geo / catalog / order fixtures for storefront feature tests.
 */
trait BuildsStorefrontFixtures
{
    protected BangladeshDistrict $district;

    protected Product $product;

    protected ProductVariant $variant;

    protected function buildStorefrontFixtures(): void
    {
        $division = BangladeshDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'is_active' => true]);

        $this->district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'code' => 'DHA-'.Str::random(4),
            'zone_type' => 'inside_dhaka',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Bags', 'slug' => 'bags-'.Str::random(4), 'is_active' => true]);
        $brand = Brand::create(['name' => 'Pearl Co', 'slug' => 'pearl-co-'.Str::random(4), 'is_active' => true]);

        $this->product = Product::create([
            'name' => 'Leather Tote Bag',
            'slug' => 'leather-tote-bag',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Italy',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'TOTE-'.Str::random(5),
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '1500.0000',
            'selling_price_bdt' => '2500.0000',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createOrder(array $overrides = [], int $quantity = 2): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => 'cash_on_delivery',
            'customer_name' => 'Nusrat Jahan',
            'customer_phone' => '01712345678',
            'division_id' => $this->district->division_id,
            'district_id' => $this->district->id,
            'delivery_zone' => 'inside_dhaka',
            'delivery_address' => 'House 7, Road 3, Dhanmondi',
            'subtotal_bdt' => bcmul('2500.0000', (string) $quantity, 4),
            'shipping_fee_bdt' => '60.0000',
            'total_bdt' => bcadd(bcmul('2500.0000', (string) $quantity, 4), '60.0000', 4),
            'payable_cod_amount_bdt' => bcadd(bcmul('2500.0000', (string) $quantity, 4), '60.0000', 4),
            'fraud_risk_score' => 10,
            'fraud_risk_level' => 'low',
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->product->name,
            'sku' => $this->variant->sku,
            'quantity' => $quantity,
            'unit_price_bdt' => '2500.0000',
            'subtotal_bdt' => bcmul('2500.0000', (string) $quantity, 4),
        ]);

        return $order->fresh(['items']);
    }
}
