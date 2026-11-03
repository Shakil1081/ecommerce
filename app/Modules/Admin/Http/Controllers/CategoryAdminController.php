<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryAdminController extends Controller
{
    /**
     * Display a listing of categories with hierarchy and product counts.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Category::with(['parent:id,name'])
            ->withCount(['products', 'children'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query->get()->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'parent_id' => $c->parent_id,
            'parent_name' => $c->parent?->name,
            'description' => $c->description,
            'is_active' => (bool) $c->is_active,
            'is_featured' => (bool) $c->is_featured,
            'sort_order' => (int) $c->sort_order,
            'products_count' => (int) $c->products_count,
            'children_count' => (int) $c->children_count,
            'created_at' => $c->created_at?->format('Y-m-d H:i'),
        ]);

        $parentOptions = Category::orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $totalCategories = Category::count();
        $rootCategories = Category::whereNull('parent_id')->count();
        $activeCategories = Category::where('is_active', true)->count();
        $featuredCategories = Category::where('is_featured', true)->count();

        return Inertia::render('Admin/Catalog/CategoryIndex', [
            'categories' => $categories,
            'parentOptions' => $parentOptions,
            'stats' => [
                'total' => $totalCategories,
                'root' => $rootCategories,
                'active' => $activeCategories,
                'featured' => $featuredCategories,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'action' => $request->input('action'),
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        // Prevent duplicate slug collision
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => ! empty($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ((int) $value === (int) $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => ! empty($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $productsCount = $category->products()->count();
        if ($productsCount > 0) {
            return redirect()->route('admin.categories.index')
                ->with('error', "Cannot delete category '{$category->name}' because {$productsCount} product(s) are assigned to it. Reassign or delete those products first.");
        }

        $childrenCount = $category->children()->count();
        if ($childrenCount > 0) {
            return redirect()->route('admin.categories.index')
                ->with('error', "Cannot delete category '{$category->name}' because it contains {$childrenCount} subcategory/subcategories. Remove or reassign them first.");
        }

        $name = $category->name;
        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', "Category '{$name}' deleted successfully.");
    }
}
