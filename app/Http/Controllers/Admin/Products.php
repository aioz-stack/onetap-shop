<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Catalog\ProductModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Products extends Controller
{
    public function __construct(private ProductModerationService $moderation) {}

    public function index(Request $request): Response
    {
        $status = ProductStatus::tryFrom((string) $request->query('status'));
        $categoryId = $request->integer('category') ?: null;
        $search = trim((string) $request->query('search'));

        $counts = ['all' => Product::query()->count()];

        foreach (ProductStatus::cases() as $case) {
            $counts[$case->value] = Product::query()->where('status', $case)->count();
        }

        $products = Product::query()
            ->with(['shop:id,name,slug', 'category:id,name', 'images' => fn ($query) => $query->where('is_primary', true)])
            ->withSum('variations as total_stock', 'stock')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($categoryId, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('product_category_id', $categoryId)
                ->orWhereIn('product_category_id', ProductCategory::query()->where('parent_id', $categoryId)->select('id'))))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhereHas('shop', fn ($shops) => $shops->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'status' => $product->status->value,
                'base_price' => $product->base_price,
                'stock' => (int) $product->total_stock,
                'sold_count' => $product->sold_count,
                'shop' => $product->shop?->only(['id', 'name']),
                'category' => $product->category?->name,
                'image' => $product->images->first()?->path,
                'updated_at' => $product->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => ['status' => $status?->value, 'category' => $categoryId, 'search' => $search],
            'counts' => $counts,
            'categories' => ProductCategory::query()->whereNull('parent_id')->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load(['shop:id,name,slug,status', 'category:id,name', 'images', 'variations', 'violations.reporter:id,name']);

        return Inertia::render('Admin/Products/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'brand' => $product->brand,
                'status' => $product->status->value,
                'rejection_reason' => $product->rejection_reason,
                'base_price' => $product->base_price,
                'weight_grams' => $product->weight_grams,
                'sold_count' => $product->sold_count,
                'rating_avg' => $product->rating_avg,
                'rating_count' => $product->rating_count,
                'published_at' => $product->published_at?->toIso8601String(),
                'created_at' => $product->created_at?->toIso8601String(),
                'shop' => $product->shop?->only(['id', 'name', 'slug']) + ['status' => $product->shop?->status->value],
                'category' => $product->category?->name,
                'images' => $product->images->sortBy('sort_order')->values()->map->only(['id', 'path', 'is_primary']),
                'variations' => $product->variations->map(fn ($variation) => $variation->only(['id', 'sku', 'label', 'price', 'compare_at_price', 'stock', 'reserved', 'is_active'])),
                'violations' => $product->violations->sortByDesc('id')->values()->map(fn ($violation) => [
                    'id' => $violation->id,
                    'reason' => $violation->reason,
                    'details' => $violation->details,
                    'status' => $violation->status,
                    'reporter' => $violation->reporter?->name,
                    'created_at' => $violation->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function approve(Request $request, Product $product): RedirectResponse
    {
        $this->moderation->approve($product, $request->user());

        return back()->with('success', "{$product->name} is now live.");
    }

    public function reject(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->moderation->reject($product, $request->user(), $validated['reason']);

        return back()->with('success', 'Product rejected. The seller will see the reason.');
    }

    public function unpublish(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->moderation->unpublish($product, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Product unpublished.');
    }

    public function flag(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->moderation->flagViolation($product, $request->user(), $validated['reason'], $validated['details'] ?? null);

        return back()->with('success', 'Violation recorded and the product was taken down.');
    }

    public function resolve(Request $request, Product $product): RedirectResponse
    {
        $this->moderation->resolveViolations($product, $request->user());

        return back()->with('success', 'Violations resolved. The product is back under review.');
    }
}
