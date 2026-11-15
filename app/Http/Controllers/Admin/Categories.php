<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\Administration\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class Categories extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $categories = ProductCategory::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $children = $categories->whereNotNull('parent_id')->groupBy('parent_id');

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories->whereNull('parent_id')->values()->map(fn (ProductCategory $parent) => $this->row($parent) + [
                'children' => ($children[$parent->id] ?? collect())->values()->map(fn (ProductCategory $child) => $this->row($child)),
            ]),
            'parents' => $categories->whereNull('parent_id')->values()->map->only(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $category = ProductCategory::query()->create($validated + ['slug' => $this->uniqueSlug($validated['name'], $validated['parent_id'] ?? null)]);
        $this->auditLogger->log('category.created', $category, newValues: $validated);
        Cache::forget('storefront.category-nav');

        return back()->with('success', "Category “{$category->name}” added.");
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);

        if (($validated['parent_id'] ?? null) && $category->children()->exists()) {
            return back()->withErrors(['parent_id' => 'A category with sub-categories cannot be moved under another category.']);
        }

        $old = $category->only(array_keys($validated));
        $category->update($validated);
        $this->auditLogger->log('category.updated', $category, $old, $validated);
        Cache::forget('storefront.category-nav');

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Only empty categories can be deleted. Deactivate it instead, or move its products and sub-categories first.');
        }

        $this->auditLogger->log('category.deleted', $category, $category->only(['name', 'slug', 'parent_id']));
        $category->delete();
        Cache::forget('storefront.category-nav');

        return back()->with('success', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ProductCategory $category): array
    {
        return $category->only(['id', 'parent_id', 'name', 'slug', 'icon', 'sort_order', 'is_active', 'products_count']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ProductCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ], ['parent_id.exists' => 'Choose a top-level category as the parent.']);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function uniqueSlug(string $name, ?int $parentId): string
    {
        $prefix = $parentId ? ProductCategory::query()->whereKey($parentId)->value('slug').'-' : '';
        $base = $prefix.Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (ProductCategory::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
