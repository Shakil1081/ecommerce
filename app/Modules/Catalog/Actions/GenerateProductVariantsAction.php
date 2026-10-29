<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GenerateProductVariantsAction
{
    /**
     * Generate Cartesian product variant matrix for a product.
     *
     * @param  array<string, array<int, string>>  $attributeOptions  e.g. ['Color' => ['Black', 'Silver'], 'Storage' => ['128GB', '256GB']]
     * @return Collection<int, ProductVariant>
     */
    public function __invoke(
        Product $product,
        array $attributeOptions,
        float $defaultSellingPrice,
        float $defaultPurchaseCost = 0.0,
        float $defaultWeight = 0.500
    ): Collection {
        if (empty($attributeOptions)) {
            // Single default variant without matrix options
            $defaultSku = strtoupper(Str::slug($product->name));
            $variant = ProductVariant::firstOrCreate(
                ['product_id' => $product->id, 'sku' => $defaultSku],
                [
                    'attributes' => [],
                    'purchase_cost_bdt' => $defaultPurchaseCost,
                    'selling_price_bdt' => $defaultSellingPrice,
                    'weight_kg' => $defaultWeight,
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                ]
            );

            return collect([$variant]);
        }

        $combinations = $this->cartesianProduct($attributeOptions);
        $createdVariants = collect();

        foreach ($combinations as $attributes) {
            // Build unique SKU suffix from attribute values
            $attrValues = array_values($attributes);
            $skuSuffix = implode('-', array_map(fn ($val) => Str::slug((string) $val), $attrValues));
            $baseSku = strtoupper(Str::slug($product->name).'-'.$skuSuffix);

            $sku = $baseSku;
            $counter = 1;
            while (ProductVariant::where('sku', $sku)->where('product_id', '!=', $product->id)->exists()) {
                $sku = "{$baseSku}-{$counter}";
                $counter++;
            }

            $variant = ProductVariant::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'sku' => $sku,
                ],
                [
                    'attributes' => $attributes,
                    'purchase_cost_bdt' => $defaultPurchaseCost,
                    'selling_price_bdt' => $defaultSellingPrice,
                    'weight_kg' => $defaultWeight,
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                ]
            );

            $createdVariants->push($variant);
        }

        return $createdVariants;
    }

    /**
     * Compute Cartesian product of key-value array options.
     *
     * @param  array<string, array<int, string>>  $input
     * @return array<int, array<string, string>>
     */
    private function cartesianProduct(array $input): array
    {
        $result = [[]];

        foreach ($input as $key => $values) {
            $append = [];
            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = (string) $item;
                    $append[] = $product;
                }
            }
            $result = $append;
        }

        return $result;
    }
}

