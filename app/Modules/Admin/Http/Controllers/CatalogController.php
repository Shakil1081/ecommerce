<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\GenerateProductVariantsAction;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
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
        $products = Product::with(['category', 'brand', 'variants'])
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'hs_code' => $validated['hs_code'] ?? null,
            'origin_country' => $validated['origin_country'],
            'warranty_policy' => $validated['warranty_policy'] ?? null,
            'description' => $validated['description'] ?? null,
            'specifications' => $validated['specifications'] ?? [],
            'is_published' => $validated['is_published'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        // Create default base variant
        $generateAction(
            $product,
            [],
            (float) $validated['default_selling_price'],
            (float) ($validated['default_purchase_cost'] ?? 0.0)
        );

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$product->name}' created successfully.");
    }

    /**
     * Show the form for editing an existing product.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Catalog/ProductForm', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'hs_code' => $product->hs_code,
                'origin_country' => $product->origin_country,
                'warranty_policy' => $product->warranty_policy,
                'description' => $product->description,
                'specifications' => $product->specifications ?? [],
                'is_published' => $product->is_published,
                'is_featured' => $product->is_featured,
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'hs_code' => ['nullable', 'string', 'max:30'],
            'origin_country' => ['required', 'string', 'max:100'],
            'warranty_policy' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        $product->update($validated);

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
