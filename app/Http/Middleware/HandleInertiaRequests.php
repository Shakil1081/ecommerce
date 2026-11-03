<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default across all Inertia views.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app' => [
                'name' => config('app.name', 'Enterprise E-Commerce'),
                'locale' => app()->getLocale(),
                'currency' => config('app.currency', 'BDT'),
                'currency_symbol' => config('app.currency_symbol', '৳'),
            ],
            'categories' => function () {
                if (! Schema::hasTable('categories')) {
                    return [];
                }

                return Cache::remember(
                    'storefront_shared_categories',
                    now()->addMinutes(30),
                    function () {
                        return Category::query()
                            ->where('is_active', true)
                            ->whereNull('parent_id')
                            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                            ->withCount('products')
                            ->orderBy('sort_order')
                            ->get()
                            ->map(fn ($cat) => [
                                'id' => (string) $cat->id,
                                'name' => $cat->name,
                                'slug' => $cat->slug,
                                'description' => $cat->description,
                                'is_featured' => (bool) $cat->is_featured,
                                'products_count' => (int) $cat->products_count,
                                'children' => $cat->children->map(fn ($child) => [
                                    'id' => (string) $child->id,
                                    'name' => $child->name,
                                    'slug' => $child->slug,
                                ])->values()->all(),
                            ])
                            ->values()
                            ->all();
                    }
                );
            },
        ];
    }
}
