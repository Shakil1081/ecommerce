<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Models\StorePage;
use App\Modules\Content\Services\StorePageBodyParser;
use App\Modules\Seo\Services\SchemaGeneratorService;
use Inertia\Inertia;
use Inertia\Response;

final class StorePageController extends Controller
{
    public function show(
        string $slug,
        StorePageBodyParser $parser,
        SchemaGeneratorService $schema,
    ): Response {
        $page = StorePage::query()->published()->where('slug', $slug)->firstOrFail();

        $canonical = url('/'.$page->slug);

        return Inertia::render('Storefront/StorePage', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'meta_description' => $page->meta_description,
                'blocks' => $parser->parse($page->body),
                'updated_at' => $page->updated_at?->toDateString(),
            ],
            'seo' => [
                'canonical' => $canonical,
                'json_ld' => [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => $slug === 'contact' ? 'ContactPage' : ($slug === 'about' ? 'AboutPage' : 'WebPage'),
                        'name' => $page->title,
                        'description' => $page->meta_description,
                        'url' => $canonical,
                    ],
                    $schema->forBreadcrumbList([
                        ['name' => 'Home', 'url' => url('/')],
                        ['name' => $page->title, 'url' => $canonical],
                    ]),
                ],
            ],
        ]);
    }
}
