<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Content\Models\StorePage;
use App\Modules\Content\Services\StorePageBodyParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class StorePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_pages_are_seeded_by_migration_and_publicly_reachable(): void
    {
        foreach (StorePage::SYSTEM_SLUGS as $slug) {
            $this->get('/'.$slug)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Storefront/StorePage')
                    ->where('page.slug', $slug)
                    ->has('page.blocks')
                    ->where('seo.canonical', url('/'.$slug)));
        }
    }

    public function test_unpublished_page_returns_404(): void
    {
        StorePage::query()->where('slug', 'terms')->update(['is_published' => false]);

        $this->get('/terms')->assertNotFound();
    }

    public function test_body_parser_produces_safe_structured_blocks(): void
    {
        $blocks = app(StorePageBodyParser::class)->parse("Intro line one\nline two\n\n## Heading\n- first\n- <script>x</script>\n\nOutro");

        $this->assertSame([
            ['type' => 'paragraph', 'text' => 'Intro line one line two'],
            ['type' => 'heading', 'text' => 'Heading'],
            ['type' => 'list', 'items' => ['first', '<script>x</script>']],
            ['type' => 'paragraph', 'text' => 'Outro'],
        ], $blocks);
    }

    public function test_sitemap_lists_real_page_and_product_urls(): void
    {
        $content = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/privacy'), $content);
        $this->assertStringContainsString(url('/returns'), $content);
        $this->assertStringNotContainsString('/privacy-policy', $content);
        $this->assertStringNotContainsString('/about-us', $content);
    }

    public function test_staff_with_settings_permission_can_edit_a_page(): void
    {
        $page = StorePage::query()->where('slug', 'contact')->firstOrFail();

        $this->actingAsStaffWith(['settings.view', 'settings.edit'])
            ->put("/admin/pages/{$page->id}", [
                'title' => 'Contact Pick & Pearl',
                'meta_description' => 'Reach our team.',
                'body' => "Call us.\n\n## Phone\n- 01700000000",
                'is_published' => true,
            ])
            ->assertRedirect('/admin/pages');

        $this->assertDatabaseHas('store_pages', ['slug' => 'contact', 'title' => 'Contact Pick & Pearl']);
    }

    public function test_staff_without_settings_edit_cannot_change_pages(): void
    {
        $page = StorePage::query()->where('slug', 'contact')->firstOrFail();

        $this->actingAsStaffWith(['settings.view'])
            ->put("/admin/pages/{$page->id}", [
                'title' => 'Hacked',
                'body' => 'x',
                'is_published' => true,
            ])
            ->assertForbidden();

        $this->get('/admin/pages')->assertOk();
    }
}
