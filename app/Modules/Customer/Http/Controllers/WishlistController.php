<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WishlistController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $items = $user->wishlistItems()
            ->with(['product' => fn ($q) => $q->with(['brand:id,name', 'media'])])
            ->latest('id')
            ->get()
            ->filter(fn (WishlistItem $item): bool => $item->product !== null && $item->product->is_published)
            ->map(function (WishlistItem $item): array {
                /** @var Product $product */
                $product = $item->product;
                $media = $product->getFirstMedia('products');

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'brand_name' => $product->brand?->name,
                    'min_price_bdt' => (string) $product->min_price_bdt,
                    'image_url' => $media?->getUrl() ?? ($product->specifications['image_url'] ?? null),
                    'added_at' => $item->created_at?->format('d M Y'),
                ];
            })
            ->values();

        return Inertia::render('Storefront/Account/Wishlist', [
            'items' => $items,
        ]);
    }

    public function toggle(Request $request, string $product): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $productModel = Product::query()->whereKey($product)->where('is_published', true)->firstOrFail();

        $existing = $user->wishlistItems()->where('product_id', $productModel->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('success', 'Removed from your wishlist.');
        }

        $user->wishlistItems()->create(['product_id' => $productModel->id]);

        return back()->with('success', 'Saved to your wishlist.');
    }
}
