<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin_category@ecommerce.test',
        ]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_view_category_listing(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/categories');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Catalog/CategoryIndex')
            ->has('categories')
            ->has('parentOptions')
            ->has('stats')
        );
    }

    public function test_admin_can_create_new_category(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/categories', [
            'name' => 'Smart Home & IoT Devices',
            'slug' => 'smart-home-iot-devices',
            'description' => 'Automated smart lighting and home gadgets',
            'sort_order' => 10,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Smart Home & IoT Devices',
            'slug' => 'smart-home-iot-devices',
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::first();

        $response = $this->actingAs($this->admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Updated Category Title',
            'slug' => $category->slug,
            'description' => 'Updated description text',
            'sort_order' => 5,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category Title',
        ]);
    }

    public function test_admin_cannot_delete_category_with_assigned_products(): void
    {
        $categoryWithProducts = Category::whereHas('products')->first();
        $this->assertNotNull($categoryWithProducts);

        $response = $this->actingAs($this->admin)->delete("/admin/categories/{$categoryWithProducts->id}");

        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $categoryWithProducts->id]);
    }

    public function test_admin_can_delete_empty_category(): void
    {
        $emptyCategory = Category::create([
            'name' => 'Empty Temporary Category',
            'slug' => 'empty-temp-category',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/categories/{$emptyCategory->id}");

        $response->assertRedirect('/admin/categories');
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $emptyCategory->id]);
    }
}
