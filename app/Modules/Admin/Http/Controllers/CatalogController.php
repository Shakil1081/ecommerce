<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\GenerateProductVariantsAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /**
     * Display a listing of catalog products.
     */
    public function index(): Response
    {
        $products = Product::with(['category', 'brand', 'variants.stocks'])
            ->withCount('variants')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'hs_code' => $p->hs_code,
                'origin_country' => $p->origin_country,
                'warranty_policy' => $p->warranty_policy,
                'category_name' => $p->category?->name ?? 'Uncategorized',
                'brand_name' => $p->brand?->name ?? 'Generic',
                'variants_count' => $p->variants_count,
                'min_price_bdt' => $p->min_price_bdt,
                'max_price_bdt' => $p->max_price_bdt,
                'total_stock' => (int) $p->variants->sum(fn ($v) => $v->available_quantity),
                'is_published' => $p->is_published,
                'is_featured' => $p->is_featured,
                'created_at' => $p->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Catalog/ProductIndex', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        if (Category::count() === 0) {
            Category::firstOrCreate(
                ['slug' => 'general'],
                ['name' => 'General Merchandise', 'is_active' => true, 'sort_order' => 1]
            );
            Category::firstOrCreate(
                ['slug' => 'smartphones-mobile-accessories'],
                ['name' => 'Smartphones & Mobile Accessories', 'is_active' => true, 'sort_order' => 2]
            );
            Category::firstOrCreate(
                ['slug' => 'consumer-electronics'],
                ['name' => 'Consumer Electronics', 'is_active' => true, 'sort_order' => 3]
            );
        }

        return Inertia::render('Admin/Catalog/ProductForm', [
            'product' => null,
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name', 'origin_country']),
            'countries' => $this->getCountryList(),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request, GenerateProductVariantsAction $generateAction): RedirectResponse
    {
        if ($request->input('brand_id') === '' || $request->input('brand_id') === '0' || $request->input('brand_id') === 0) {
            $request->merge(['brand_id' => null]);
        }
        if ($request->input('hs_code') === '') {
            $request->merge(['hs_code' => null]);
        }
        if ($request->input('warranty_policy') === '') {
            $request->merge(['warranty_policy' => null]);
        }
        if ($request->input('description') === '') {
            $request->merge(['description' => null]);
        }
        if ($request->input('slug') === '') {
            $request->merge(['slug' => null]);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'hs_code' => ['nullable', 'string', 'max:30'],
            'origin_country' => ['required', 'string', 'max:100'],
            'warranty_policy' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'default_selling_price' => ['required', 'numeric', 'min:0'],
            'default_purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode_ean' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string'],
        ]);

        $productSlug = !empty($validated['slug'])
            ? \Illuminate\Support\Str::slug($validated['slug'])
            : \Illuminate\Support\Str::slug($validated['name']);

        $product = Product::create([
            'name' => $validated['name'],
            'slug' => $productSlug,
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'hs_code' => $validated['hs_code'] ?? null,
            'origin_country' => $validated['origin_country'],
            'warranty_policy' => $validated['warranty_policy'] ?? null,
            'description' => $validated['description'] ?? null,
            'specifications' => $validated['specifications'] ?? [],
            'is_published' => $validated['is_published'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);

        // Attach image if uploaded or URL provided
        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')->toMediaCollection('products');
        } elseif (!empty($validated['image_url'])) {
            try {
                $product->addMediaFromUrl($validated['image_url'])->toMediaCollection('products');
            } catch (\Throwable) {
                // Ignore if sandbox or network blocks external image fetch
            }
        }

        // Create default base variant
        $variants = $generateAction(
            $product,
            [],
            (float) $validated['default_selling_price'],
            (float) ($validated['default_purchase_cost'] ?? 0.0),
            (float) ($validated['weight_kg'] ?? 0.500)
        );

        $baseVariant = $variants->first();
        if ($baseVariant) {
            $variantUpdates = [];
            if (!empty($validated['sku'])) {
                $variantUpdates['sku'] = strtoupper($validated['sku']);
            }
            if (!empty($validated['barcode_ean'])) {
                $variantUpdates['barcode_ean'] = $validated['barcode_ean'];
            }
            if (isset($validated['compare_at_price'])) {
                $variantUpdates['compare_at_price_bdt'] = (float) $validated['compare_at_price'];
            }
            if (!empty($variantUpdates)) {
                $baseVariant->update($variantUpdates);
            }

            // Assign initial stock to primary warehouse (supports 0 or any positive integer)
            $initialStock = isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : 0;
            $this->syncVariantStock($baseVariant, $initialStock);
        }

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$product->name}' created successfully.");
    }

    /**
     * Show the form for editing an existing product.
     */
    public function edit(Product $product): Response
    {
        $defaultVariant = $product->defaultVariant ?? $product->variants()->first();
        if ($defaultVariant) {
            $defaultVariant->loadMissing('stocks');
        }

        return Inertia::render('Admin/Catalog/ProductForm', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'hs_code' => $product->hs_code,
                'origin_country' => $product->origin_country,
                'warranty_policy' => $product->warranty_policy,
                'description' => $product->description,
                'specifications' => $product->specifications ?? [],
                'is_published' => $product->is_published,
                'is_featured' => $product->is_featured,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'image_url' => $product->getFirstMediaUrl('products') ?: $product->getFirstMediaUrl('thumbnail'),
                'default_selling_price' => $defaultVariant ? (float) $defaultVariant->selling_price_bdt : 0,
                'default_purchase_cost' => $defaultVariant ? (float) $defaultVariant->purchase_cost_bdt : 0,
                'compare_at_price' => $defaultVariant ? (float) ($defaultVariant->compare_at_price_bdt ?? 0) : 0,
                'sku' => $defaultVariant?->sku ?? '',
                'barcode_ean' => $defaultVariant?->barcode_ean ?? '',
                'weight_kg' => $defaultVariant ? (float) $defaultVariant->weight_kg : 0.5,
                'stock_quantity' => $defaultVariant ? $defaultVariant->available_quantity : 0,
            ],
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name', 'origin_country']),
            'countries' => $this->getCountryList(),
        ]);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        if ($request->input('brand_id') === '' || $request->input('brand_id') === '0' || $request->input('brand_id') === 0) {
            $request->merge(['brand_id' => null]);
        }
        if ($request->input('hs_code') === '') {
            $request->merge(['hs_code' => null]);
        }
        if ($request->input('warranty_policy') === '') {
            $request->merge(['warranty_policy' => null]);
        }
        if ($request->input('description') === '') {
            $request->merge(['description' => null]);
        }
        if ($request->input('slug') === '') {
            $request->merge(['slug' => null]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'hs_code' => ['nullable', 'string', 'max:30'],
            'origin_country' => ['required', 'string', 'max:100'],
            'warranty_policy' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'default_purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode_ean' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['slug']);
        }

        $product->update($validated);

        if ($request->hasFile('image')) {
            $product->clearMediaCollection('products');
            $product->addMediaFromRequest('image')->toMediaCollection('products');
        } elseif (!empty($validated['image_url']) && $validated['image_url'] !== $product->getFirstMediaUrl('products')) {
            try {
                $product->clearMediaCollection('products');
                $product->addMediaFromUrl($validated['image_url'])->toMediaCollection('products');
            } catch (\Throwable) {
                // Ignore if sandbox or network blocks external image fetch
            }
        }

        // Update default variant if prices, sku, or stock quantity changed
        $defaultVariant = $product->defaultVariant ?? $product->variants()->first();
        if ($defaultVariant) {
            $vUpdates = [];
            if (isset($validated['default_selling_price'])) {
                $vUpdates['selling_price_bdt'] = (float) $validated['default_selling_price'];
            }
            if (isset($validated['default_purchase_cost'])) {
                $vUpdates['purchase_cost_bdt'] = (float) $validated['default_purchase_cost'];
            }
            if (isset($validated['compare_at_price'])) {
                $vUpdates['compare_at_price_bdt'] = (float) $validated['compare_at_price'];
            }
            if (!empty($validated['sku'])) {
                $vUpdates['sku'] = strtoupper($validated['sku']);
            }
            if (!empty($validated['barcode_ean'])) {
                $vUpdates['barcode_ean'] = $validated['barcode_ean'];
            }
            if (isset($validated['weight_kg'])) {
                $vUpdates['weight_kg'] = (float) $validated['weight_kg'];
            }
            if (!empty($vUpdates)) {
                $defaultVariant->update($vUpdates);
            }

            // Sync inventory stock quantity
            if (isset($validated['stock_quantity'])) {
                $this->syncVariantStock($defaultVariant, (int) $validated['stock_quantity']);
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$product->name}' updated successfully.");
    }

    /**
     * Delete an existing product and its variants.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$name}' deleted successfully.");
    }

    /**
     * Show the Matrix Variant Editor for a product.
     */
    public function matrix(Product $product): Response
    {
        $product->load(['category', 'brand', 'variants']);

        return Inertia::render('Admin/Catalog/ProductMatrixEditor', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category_name' => $product->category?->name ?? 'Uncategorized',
                'brand_name' => $product->brand?->name ?? 'Generic',
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'barcode_ean' => $v->barcode_ean,
                    'attributes' => $v->attributes,
                    'purchase_cost_bdt' => (float) $v->purchase_cost_bdt,
                    'selling_price_bdt' => (float) $v->selling_price_bdt,
                    'weight_kg' => (float) $v->weight_kg,
                    'is_active' => $v->is_active,
                ]),
            ],
        ]);
    }

    /**
     * Generate Cartesian product variants from submitted attribute matrix.
     */
    public function generateVariants(
        Request $request,
        Product $product,
        GenerateProductVariantsAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'attributes' => ['required', 'array'],
            'attributes.*' => ['required', 'array'],
            'selling_price_bdt' => ['required', 'numeric', 'min:0'],
            'purchase_cost_bdt' => ['nullable', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action(
            $product,
            $validated['attributes'],
            (float) $validated['selling_price_bdt'],
            (float) ($validated['purchase_cost_bdt'] ?? 0.0),
            (float) ($validated['weight_kg'] ?? 0.500)
        );

        return redirect()->route('admin.products.matrix', $product)
            ->with('success', 'Product variants generated successfully.');
    }

    /**
     * Retrieve or automatically initialize the primary central fulfillment warehouse.
     */
    private function getOrCreatePrimaryWarehouse(): Warehouse
    {
        return Warehouse::where('is_primary', true)->first()
            ?? Warehouse::first()
            ?? Warehouse::create([
                'code' => 'DHK-CENTRAL',
                'name' => 'Dhaka Central Distribution Hub (Tejgaon)',
                'address' => 'Plot 42, Tejgaon Industrial Area, Dhaka-1208',
                'contact_number' => '+8801711000000',
                'is_primary' => true,
                'is_active' => true,
            ]);
    }

    /**
     * Synchronize inventory stock quantity for a variant in the primary warehouse.
     */
    private function syncVariantStock(ProductVariant $variant, int $quantity): void
    {
        $primaryWarehouse = $this->getOrCreatePrimaryWarehouse();

        InventoryStock::updateOrCreate(
            [
                'warehouse_id' => $primaryWarehouse->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'physical_quantity' => max(0, $quantity),
                'reserved_quantity' => 0,
                'bin_location' => 'MAIN-01',
            ]
        );
    }

    /**
     * Common trading origin country list.
     *
     * @return array<int, string>
     */
    private function getCountryList(): array
    {
        return [
            'Bangladesh',
            'China',
            'United Arab Emirates',
            'United States',
            'United Kingdom',
            'Japan',
            'Germany',
            'South Korea',
            'India',
            'Thailand',
            'Vietnam',
            'Malaysia',
            'Singapore',
        ];
    }
}
