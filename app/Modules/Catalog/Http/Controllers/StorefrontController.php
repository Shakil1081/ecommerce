<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Display the customer-facing Shop & Catalog browsing page.
     */
    public function index(Request $request): Response
    {
        $categorySlug = $request->input('category');
        $brandSlug = $request->input('brand');
        $search = $request->input('search');
        $sort = $request->input('sort', 'featured');

        $query = Product::query()
            ->where('is_published', true)
            ->with(['category', 'brand', 'variants.stocks', 'media']);

        if (! empty($categorySlug)) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if (! empty($brandSlug)) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug));
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('origin_country', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn ($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
        }

        switch ($sort) {
            case 'price_low':
                $query->orderBy('created_at', 'desc'); // will sort client-side by min_price
                break;
            case 'price_high':
                $query->orderBy('created_at', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'featured':
            default:
                $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->get()->map(function (Product $p) {
            $variants = $p->variants->where('is_active', true);
            $firstVariant = $variants->first();

            // Extract unique colors and sizes
            $colors = [];
            $sizes = [];
            foreach ($variants as $v) {
                $attrs = $v->attributes ?? [];
                if (isset($attrs['Color']) && ! in_array($attrs['Color'], $colors, true)) {
                    $colors[] = $attrs['Color'];
                }
                if (isset($attrs['Size']) && ! in_array($attrs['Size'], $sizes, true)) {
                    $sizes[] = $attrs['Size'];
                }
                if (isset($attrs['Storage']) && ! in_array($attrs['Storage'], $sizes, true)) {
                    $sizes[] = $attrs['Storage'];
                }
            }

            // Fallback image based on category
            $defaultThumb = match ($p->category?->slug) {
                'smartphones-mobile-accessories' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=600&auto=format&fit=crop&q=80',
                'consumer-electronics' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80',
                'computers-office-it' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&auto=format&fit=crop&q=80',
                'home-kitchen-appliances' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=600&auto=format&fit=crop&q=80',
                default => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80',
            };

            $imageUrl = $p->getFirstMediaUrl('products', 'card_600');
            if (empty($imageUrl)) {
                $imageUrl = $p->getFirstMediaUrl('products') ?: $defaultThumb;
            }

            $totalStock = $variants->sum(fn ($v) => $v->available_quantity);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'category_name' => $p->category?->name ?? 'General',
                'category_slug' => $p->category?->slug ?? '',
                'brand_name' => $p->brand?->name ?? 'Generic',
                'origin_country' => $p->origin_country,
                'warranty_policy' => $p->warranty_policy,
                'min_price_bdt' => $p->min_price_bdt,
                'max_price_bdt' => $p->max_price_bdt,
                'compare_at_price_bdt' => $firstVariant?->compare_at_price_bdt,
                'image_url' => $imageUrl,
                'variants_count' => $variants->count(),
                'available_colors' => $colors,
                'available_sizes' => $sizes,
                'in_stock' => $totalStock > 0,
                'total_stock' => $totalStock,
                'default_variant_id' => $firstVariant?->id,
                'is_featured' => $p->is_featured,
            ];
        });

        // Client-side sort if price sorted
        if ($sort === 'price_low') {
            $products = $products->sortBy('min_price_bdt')->values();
        } elseif ($sort === 'price_high') {
            $products = $products->sortByDesc('max_price_bdt')->values();
        }

        $categories = Category::active()
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description']);

        $brands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'origin_country']);

        return Inertia::render('Storefront/Shop', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'category' => $categorySlug,
                'brand' => $brandSlug,
                'search' => $search,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Display the Product Detail Page (PDP) with matrix variants, swatches, and specifications.
     */
    public function show(string $slug): Response
    {
        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->with(['category', 'brand', 'variants.stocks', 'media', 'approvedReviews.user'])
            ->firstOrFail();

        $activeVariants = $product->variants->where('is_active', true)->values();

        // Extract option keys and values (e.g. Color => [...], Storage/Size => [...])
        $optionGroups = [];
        foreach ($activeVariants as $v) {
            $attrs = $v->attributes ?? [];
            foreach ($attrs as $optName => $optVal) {
                if (! isset($optionGroups[$optName])) {
                    $optionGroups[$optName] = [];
                }
                if (! in_array($optVal, $optionGroups[$optName], true)) {
                    $optionGroups[$optName][] = $optVal;
                }
            }
        }

        // Default gallery fallback
        $defaultThumb = match ($product->category?->slug) {
            'smartphones-mobile-accessories' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=1200&auto=format&fit=crop&q=80',
            'consumer-electronics' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1200&auto=format&fit=crop&q=80',
            'computers-office-it' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=1200&auto=format&fit=crop&q=80',
            'home-kitchen-appliances' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=1200&auto=format&fit=crop&q=80',
            default => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1200&auto=format&fit=crop&q=80',
        };

        $gallery = [];
        $mediaItems = $product->getMedia('products');
        if ($mediaItems->isNotEmpty()) {
            foreach ($mediaItems as $media) {
                $gallery[] = [
                    'thumb' => $media->getUrl('thumb_300') ?: $media->getUrl(),
                    'full' => $media->getUrl('gallery_1200') ?: $media->getUrl(),
                ];
            }
        } else {
            $gallery[] = [
                'thumb' => $defaultThumb,
                'full' => $defaultThumb,
            ];
        }

        // Map variants with detailed stock
        $mappedVariants = $activeVariants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'sku' => $v->sku,
            'barcode' => $v->barcode_ean,
            'attributes' => $v->attributes ?? [],
            'selling_price_bdt' => (float) $v->selling_price_bdt,
            'compare_at_price_bdt' => $v->compare_at_price_bdt ? (float) $v->compare_at_price_bdt : null,
            'weight_kg' => (float) $v->weight_kg,
            'available_quantity' => $v->available_quantity,
            'in_stock' => $v->available_quantity > 0,
        ]);

        // Related products
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_published', true)
            ->with(['brand', 'variants'])
            ->take(4)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'brand_name' => $p->brand?->name,
                'min_price_bdt' => $p->min_price_bdt,
                'image_url' => $defaultThumb,
            ]);

        return Inertia::render('Storefront/ProductDetail', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'specifications' => $product->specifications ?? [],
                'category_name' => $product->category?->name,
                'category_slug' => $product->category?->slug,
                'brand_name' => $product->brand?->name,
                'origin_country' => $product->origin_country,
                'warranty_policy' => $product->warranty_policy,
                'hs_code' => $product->hs_code,
                'min_price_bdt' => $product->min_price_bdt,
                'max_price_bdt' => $product->max_price_bdt,
                'average_rating' => $product->average_rating,
                'review_count' => $product->review_count,
            ],
            'variants' => $mappedVariants,
            'optionGroups' => $optionGroups,
            'gallery' => $gallery,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
