<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductReview;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use App\Modules\Logistics\Actions\CalculateShippingRateAction;
use App\Modules\Seo\Services\SchemaGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Display the customer-facing Home Page with Best Selling, New Arrivals, Paginated All Products, and Why Us sections.
     */
    public function home(Request $request): Response
    {
        $categorySlug = $request->input('category');
        $perPage = 12;

        $allProductsQuery = Product::query()
            ->where('is_published', true)
            ->with(['category:id,name,slug', 'brand:id,name,slug,origin_country', 'variants.stocks', 'media']);

        if (! empty($categorySlug) && $categorySlug !== 'all') {
            $allProductsQuery->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $allProductsQuery->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');

        $paginated = $allProductsQuery->paginate($perPage)->withQueryString();

        $allProducts = $paginated->getCollection()->map(fn (Product $p) => $this->formatProductCard($p))->values();

        $pagination = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'has_more_pages' => $paginated->hasMorePages(),
            'prev_page_url' => $paginated->previousPageUrl(),
            'next_page_url' => $paginated->nextPageUrl(),
            'links' => $paginated->linkCollection()->toArray(),
        ];

        // Best Selling & New Arrivals - evaluated on full page render, skipped on partial pagination reloads
        $bestSellingProducts = fn () => Product::query()
            ->where('is_published', true)
            ->where('is_featured', true)
            ->with(['category:id,name,slug', 'brand:id,name,slug,origin_country', 'variants.stocks', 'media'])
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get()
            ->map(fn (Product $p) => $this->formatProductCard($p))
            ->whenEmpty(function () {
                return Product::query()
                    ->where('is_published', true)
                    ->with(['category:id,name,slug', 'brand:id,name,slug,origin_country', 'variants.stocks', 'media'])
                    ->orderBy('created_at', 'desc')
                    ->take(8)
                    ->get()
                    ->map(fn (Product $p) => $this->formatProductCard($p));
            });

        $newArrivals = fn () => Product::query()
            ->where('is_published', true)
            ->with(['category:id,name,slug', 'brand:id,name,slug,origin_country', 'variants.stocks', 'media'])
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get()
            ->map(fn (Product $p) => $this->formatProductCard($p));

        $categories = fn () => Category::active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'is_featured', 'sort_order']);

        $brands = fn () => Brand::active()
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('name')
            ->take(8)
            ->get(['id', 'name', 'slug', 'origin_country']);

        return Inertia::render('Storefront/Home', [
            'bestSellingProducts' => $bestSellingProducts,
            'newArrivals' => $newArrivals,
            'allProducts' => $allProducts,
            'pagination' => $pagination,
            'categories' => $categories,
            'brands' => $brands,
            'activeCategory' => $categorySlug ?: 'all',
        ]);
    }

    /**
     * Display the customer-facing Shop & Catalog browsing page.
     */
    public function index(Request $request): Response
    {
        $categorySlug = $request->input('category');
        $brandSlug = $request->input('brand');
        $search = $request->input('search');
        $sort = $request->input('sort', 'featured');
        $color = $request->input('color');
        $minPrice = $request->filled('min_price') ? (float) $request->input('min_price') : null;
        $maxPrice = $request->filled('max_price') ? (float) $request->input('max_price') : null;
        $inStock = $request->boolean('in_stock');
        $warranty = $request->boolean('warranty');

        $query = Product::query()
            ->where('is_published', true)
            ->with(['category', 'brand', 'variants.stocks', 'media']);

        if (! empty($categorySlug) && $categorySlug !== 'all') {
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

        if ($minPrice !== null && $minPrice > 0) {
            $query->whereHas('variants', fn ($vq) => $vq->where('is_active', true)->where('selling_price_bdt', '>=', $minPrice));
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $query->whereHas('variants', fn ($vq) => $vq->where('is_active', true)->where('selling_price_bdt', '<=', $maxPrice));
        }

        if (! empty($color)) {
            $query->whereHas('variants', function ($vq) use ($color) {
                $vq->where('is_active', true)
                    ->where(function ($sub) use ($color) {
                        $sub->where('attributes->Color', $color)
                            ->orWhere('attributes->Color', 'like', "%{$color}%");
                    });
            });
        }

        if ($inStock) {
            $query->whereHas('variants.stocks', fn ($sq) => $sq->where('physical_quantity', '>', 0));
        }

        if ($warranty) {
            $query->whereNotNull('warranty_policy')->where('warranty_policy', '<>', '');
        }

        switch ($sort) {
            case 'price_low':
                $query->orderBy(
                    ProductVariant::select('selling_price_bdt')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('is_active', true)
                        ->orderBy('selling_price_bdt', 'asc')
                        ->limit(1),
                    'asc'
                );
                break;
            case 'price_high':
                $query->orderBy(
                    ProductVariant::select('selling_price_bdt')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('is_active', true)
                        ->orderBy('selling_price_bdt', 'desc')
                        ->limit(1),
                    'desc'
                );
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'featured':
            default:
                $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
                break;
        }

        $perPage = max(1, min(50, (int) $request->input('per_page', 12)));
        $paginated = $query->paginate($perPage)->withQueryString();

        $products = $paginated->getCollection()->map(fn (Product $p) => $this->formatProductCard($p))->values();

        $categories = Category::active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'is_featured', 'sort_order']);

        $brands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'origin_country']);

        $availableColors = Cache::remember('storefront_shop_colors', 3600, function () {
            $variants = ProductVariant::query()
                ->where('is_active', true)
                ->whereNotNull('attributes')
                ->pluck('attributes');

            $colors = [];
            foreach ($variants as $attrs) {
                if (is_array($attrs) && ! empty($attrs['Color'])) {
                    $c = trim((string) $attrs['Color']);
                    if ($c !== '' && ! in_array($c, $colors, true)) {
                        $colors[] = $c;
                    }
                }
            }
            natcasesort($colors);

            return array_values($colors);
        });

        $priceBounds = Cache::remember('storefront_shop_price_bounds', 3600, function () {
            $min = (float) (ProductVariant::query()->where('is_active', true)->min('selling_price_bdt') ?? 0);
            $max = (float) (ProductVariant::query()->where('is_active', true)->max('selling_price_bdt') ?? 100000);

            return [
                'min' => (int) floor($min),
                'max' => (int) ceil($max),
            ];
        });

        return Inertia::render('Storefront/Shop', [
            'products' => $products,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'has_more_pages' => $paginated->hasMorePages(),
                'prev_page_url' => $paginated->previousPageUrl(),
                'next_page_url' => $paginated->nextPageUrl(),
                'links' => $paginated->linkCollection()->toArray(),
            ],
            'categories' => $categories,
            'brands' => $brands,
            'availableColors' => $availableColors,
            'priceBounds' => $priceBounds,
            'filters' => [
                'category' => $categorySlug,
                'brand' => $brandSlug,
                'search' => $search,
                'sort' => $sort,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'color' => $color,
                'in_stock' => $inStock,
                'warranty' => $warranty,
                'page' => $paginated->currentPage(),
            ],
        ]);
    }

    /**
     * Display the Product Detail Page (PDP) with matrix variants, swatches, and specifications.
     */
    public function show(
        Request $request,
        string $slug,
        HoneypotTimingGuardService $timingGuard,
        CalculateShippingRateAction $shippingRates,
        SchemaGeneratorService $schema,
    ): Response {
        $product = Product::where('slug', $slug)
            ->where('is_published', true)
            ->with(['category', 'brand', 'variants.stocks', 'media', 'approvedReviews.user'])
            ->firstOrFail();

        $activeVariants = $product->variants->where('is_active', true)->values();

        // Build option groups map
        $optionGroups = [];
        foreach ($activeVariants as $variant) {
            $attributes = $variant->attributes ?? [];
            foreach ($attributes as $attrName => $attrValue) {
                if (! isset($optionGroups[$attrName])) {
                    $optionGroups[$attrName] = [];
                }
                if (! in_array($attrValue, $optionGroups[$attrName], true)) {
                    $optionGroups[$attrName][] = $attrValue;
                }
            }
        }

        // Build gallery images
        $gallery = [];
        $mediaItems = $product->getMedia('products');
        if ($mediaItems->isNotEmpty()) {
            foreach ($mediaItems as $media) {
                $gallery[] = [
                    'thumb' => ($media->hasGeneratedConversion('thumb_300') ? $media->getUrl('thumb_300') : null) ?: $media->getUrl(),
                    'full' => ($media->hasGeneratedConversion('gallery_1200') ? $media->getUrl('gallery_1200') : null) ?: $media->getUrl(),
                ];
            }
        } elseif (! empty($product->specifications['image_url'])) {
            $gallery[] = [
                'thumb' => $product->specifications['image_url'],
                'full' => $product->specifications['image_url'],
            ];
            if (! empty($product->specifications['gallery_urls']) && is_array($product->specifications['gallery_urls'])) {
                foreach ($product->specifications['gallery_urls'] as $gUrl) {
                    $gallery[] = [
                        'thumb' => $gUrl,
                        'full' => $gUrl,
                    ];
                }
            }
        } else {
            $defaultThumb = match ($product->category?->slug) {
                'ladies-bags', 'womens-bags', 'fashion-lifestyle' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=1200&auto=format&fit=crop&q=80',
                'smartphones-mobile-accessories' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=1200&auto=format&fit=crop&q=80',
                'consumer-electronics' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1200&auto=format&fit=crop&q=80',
                'computers-office-it' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=1200&auto=format&fit=crop&q=80',
                'home-kitchen-appliances' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=1200&auto=format&fit=crop&q=80',
                default => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1200&auto=format&fit=crop&q=80',
            };
            $gallery[] = [
                'thumb' => $defaultThumb,
                'full' => $defaultThumb,
            ];
        }

        // Related items from same category
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_published', true)
            ->take(4)
            ->get()
            ->map(fn (Product $p) => $this->formatProductCard($p));

        // Real approved-review aggregates (never placeholder numbers: they feed Google rich results)
        $approvedReviews = $product->approvedReviews->sortByDesc('created_at')->values();
        $reviewCount = $approvedReviews->count();
        $averageRating = $reviewCount > 0 ? round((float) $approvedReviews->avg('rating'), 1) : 0.0;

        $user = $request->user();
        $isWishlisted = $user !== null && $user->wishlistItems()->where('product_id', $product->id)->exists();

        $canonical = route('storefront.product.show', ['slug' => $product->slug]);
        $firstVariant = $activeVariants->first();
        $inStock = $activeVariants->contains(fn ($v) => $v->available_quantity > 0);
        $metaDescription = Str::limit(trim(strip_tags((string) $product->description)) ?: "Buy genuine {$product->name} online in Bangladesh with fast delivery and Cash on Delivery.", 155);

        return Inertia::render('Storefront/ProductDetail', [
            // Signed render timestamp for the 1-click fast-order bot timing guard (ensure.human)
            'timingToken' => $timingGuard->generateTimingToken(),
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
                'average_rating' => $averageRating,
                'review_count' => $reviewCount,
            ],
            'reviews' => $approvedReviews->take(20)->map(fn (ProductReview $review): array => [
                'id' => $review->id,
                'customer_name' => $review->customer_name,
                'rating' => $review->rating,
                'title' => $review->title,
                'review_text' => $review->review_text,
                'is_verified_purchase' => $review->is_verified_purchase,
                'created_at' => $review->created_at?->format('d M Y'),
            ])->values(),
            'ratingBreakdown' => collect([5, 4, 3, 2, 1])
                ->mapWithKeys(fn (int $stars): array => [$stars => $approvedReviews->where('rating', $stars)->count()]),
            'isWishlisted' => $isWishlisted,
            // Admin-configured zone rates (CalculateShippingRateAction), never hard-coded fees
            'shippingRates' => $shippingRates->baseRatesByZone(),
            'freeShippingThreshold' => CartResource::FREE_SHIPPING_THRESHOLD,
            'seo' => [
                'canonical' => $canonical,
                'description' => $metaDescription,
                'image' => $gallery[0]['full'] ?? null,
                'json_ld' => [
                    $schema->forProduct(
                        name: $product->name,
                        description: $metaDescription,
                        sku: (string) ($firstVariant?->sku ?? $product->id),
                        images: array_values(array_map(fn (array $g): string => $g['full'], $gallery)),
                        price: (float) ($firstVariant?->selling_price_bdt ?? $product->min_price_bdt ?? 0),
                        availability: $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        brand: $product->brand?->name,
                        ratingValue: $reviewCount > 0 ? $averageRating : null,
                        reviewCount: $reviewCount > 0 ? $reviewCount : null,
                        url: $canonical,
                    ),
                    $schema->forBreadcrumbList(array_values(array_filter([
                        ['name' => 'Home', 'url' => url('/')],
                        ['name' => 'Shop', 'url' => route('storefront.shop')],
                        $product->category ? ['name' => $product->category->name, 'url' => route('storefront.shop', ['category' => $product->category->slug])] : null,
                        ['name' => $product->name, 'url' => $canonical],
                    ]))),
                ],
            ],
            'variants' => $activeVariants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'attributes' => $v->attributes ?? [],
                'selling_price_bdt' => $v->selling_price_bdt,
                'compare_at_price_bdt' => $v->compare_at_price_bdt,
                'weight_kg' => (float) $v->weight_kg,
                'available_quantity' => $v->available_quantity,
                'in_stock' => $v->available_quantity > 0,
            ]),
            'optionGroups' => $optionGroups,
            'gallery' => $gallery,
            'relatedProducts' => $relatedProducts,
        ]);
    }

    /**
     * Format a product instance into a standard product card DTO array.
     *
     * @return array<string, mixed>
     */
    private function formatProductCard(Product $p): array
    {
        $variants = $p->variants->where('is_active', true);
        $firstVariant = $variants->first();

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

        $defaultThumb = match ($p->category?->slug) {
            'ladies-bags', 'womens-bags', 'fashion-lifestyle' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&auto=format&fit=crop&q=80',
            'smartphones-mobile-accessories' => 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=600&auto=format&fit=crop&q=80',
            'consumer-electronics' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80',
            'computers-office-it' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&auto=format&fit=crop&q=80',
            'home-kitchen-appliances' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=600&auto=format&fit=crop&q=80',
            default => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80',
        };

        $firstMedia = $p->getFirstMedia('products');
        $imageUrl = null;
        if ($firstMedia) {
            $imageUrl = ($firstMedia->hasGeneratedConversion('card_600') ? $firstMedia->getUrl('card_600') : null) ?: $firstMedia->getUrl();
        } else {
            $imageUrl = $p->getFirstMediaUrl('thumbnail') ?: ($p->specifications['image_url'] ?? $defaultThumb);
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
    }
}
