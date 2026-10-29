<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_creates_with_auto_generated_slug_and_hierarchy(): void
    {
        // 1. Root category
        $root = Category::create([
            'name' => 'Consumer Electronics',
            'description' => 'Imported gadgets and domestic electronics.',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $this->assertEquals('consumer-electronics', $root->slug);
        $this->assertTrue($root->is_featured);

        // 2. Child category
        $child = Category::create([
            'parent_id' => $root->id,
            'name' => 'Smart Watches & Bands',
            'is_active' => true,
        ]);

        $this->assertEquals('smart-watches-bands', $child->slug);
        $this->assertEquals($root->id, $child->parent->id);
        $this->assertCount(1, $root->children);
        $this->assertEquals('Smart Watches & Bands', $root->children->first()->name);
    }

    public function test_category_scopes_filter_records_correctly(): void
    {
        $activeRoot = Category::create([
            'name' => 'Active Root Category',
            'is_active' => true,
            'is_featured' => true,
        ]);

        $inactiveRoot = Category::create([
            'name' => 'Inactive Root Category',
            'is_active' => false,
            'is_featured' => false,
        ]);

        $child = Category::create([
            'parent_id' => $activeRoot->id,
            'name' => 'Subcategory',
            'is_active' => true,
            'is_featured' => false,
        ]);

        $roots = Category::root()->get();
        $this->assertTrue($roots->contains($activeRoot));
        $this->assertTrue($roots->contains($inactiveRoot));
        $this->assertFalse($roots->contains($child));

        $activeOnly = Category::active()->get();
        $this->assertTrue($activeOnly->contains($activeRoot));
        $this->assertFalse($activeOnly->contains($inactiveRoot));

        $featured = Category::featured()->get();
        $this->assertTrue($featured->contains($activeRoot));
        $this->assertFalse($featured->contains($child));
    }

    public function test_brand_model_tracks_origin_country_and_media_collections(): void
    {
        $brand = Brand::create([
            'name' => 'Anker Innovations',
            'origin_country' => 'China',
            'website_url' => 'https://anker.com',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $this->assertEquals('anker-innovations', $brand->slug);
        $this->assertEquals('China', $brand->origin_country);
        $this->assertTrue($brand->is_featured);

        // Verify media collection registration
        $brand->registerMediaCollections();
        $collections = collect($brand->mediaCollections);

        $collectionNames = $collections->pluck('name')->toArray();
        $this->assertContains('logo', $collectionNames);
        $this->assertContains('banner', $collectionNames);
    }
}
