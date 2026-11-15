<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariation;
use App\Services\Catalog\SellerProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Products extends SellerController
{
    public function __construct(private SellerProductService $products) {}

    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $status = ProductStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));
        $counts = ['all' => $shop->products()->count()];

        foreach (ProductStatus::cases() as $case) {
            $counts[$case->value] = $shop->products()->where('status', $case)->count();
        }

        return Inertia::render('Seller/Products/Index', [
            'products' => $shop->products()
                ->with(['primaryImage', 'category:id,name'])
                ->withSum('variations as stock_total', 'stock')
                ->withSum('variations as reserved_total', 'reserved')
                ->withCount('variations')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->latest('updated_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'status' => $product->status->value,
                    'rejection_reason' => $product->rejection_reason,
                    'price' => $product->base_price,
                    'available' => max(0, (int) $product->stock_total - (int) $product->reserved_total),
                    'variations_count' => $product->variations_count,
                    'sold_count' => $product->sold_count,
                    'category' => $product->category?->name,
                    'image' => $product->primaryImage?->path,
                    'published' => (bool) $product->published_at,
                    'updated_at' => $product->updated_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $status?->value, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Seller/Products/Form', ['product' => null, 'categories' => $this->categoryOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, creating: true);
        $product = $this->products->create($this->shop($request), $request->user(), $validated, $request->file('images', []), $request->boolean('submit'));

        return redirect()->route('seller.products.index')->with('success', $product->status === ProductStatus::UnderReview ? "“{$product->name}” was sent for review." : "“{$product->name}” saved as a draft.");
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorizeProduct($request, $product);
        $product->load(['images' => fn ($query) => $query->orderBy('sort_order'), 'variations' => fn ($query) => $query->orderByDesc('is_default')->orderBy('id'), 'violations' => fn ($query) => $query->where('status', 'open')]);

        return Inertia::render('Seller/Products/Form', [
            'product' => $product->only(['id', 'name', 'slug', 'description', 'brand', 'product_category_id', 'weight_grams', 'rejection_reason']) + [
                'status' => $product->status->value,
                'images' => $product->images->map(fn ($image) => ['id' => $image->id, 'url' => asset('storage/'.$image->path), 'is_primary' => $image->is_primary]),
                'variations' => $product->variations->map(fn (ProductVariation $variation) => $variation->only(['id', 'label', 'price', 'compare_at_price', 'stock', 'reserved', 'sku', 'is_active'])),
                'violations' => $product->violations->map->only(['reason', 'details']),
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $validated = $this->validated($request, creating: false);

        $updated = $this->products->update(
            $product,
            $request->user(),
            $validated,
            $request->file('images', []),
            array_map('intval', $validated['remove_images'] ?? []),
            isset($validated['primary_image_id']) ? (int) $validated['primary_image_id'] : null,
            $request->boolean('submit'),
        );

        $message = $updated->status === ProductStatus::UnderReview ? 'Saved. The changes were sent for review.' : 'Product saved.';

        return redirect()->route('seller.products.edit', $product)->with('success', $message);
    }

    public function submit(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->products->submit($product, $request->user());

        return back()->with('success', 'Sent for review.');
    }

    public function unpublish(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->products->unpublish($product, $request->user());

        return back()->with('success', 'Product hidden from the storefront.');
    }

    public function republish(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $result = $this->products->republish($product, $request->user());

        return back()->with('success', $result->status === ProductStatus::Live ? 'Product is live again.' : 'Sent for review.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->products->delete($product, $request->user());

        return redirect()->route('seller.products.index')->with('success', 'Product deleted. Past orders keep their details.');
    }

    public function inventory(Request $request): Response
    {
        $shop = $this->shop($request);
        $search = trim((string) $request->query('search'));
        $low = $request->boolean('low');

        return Inertia::render('Seller/Products/Inventory', [
            'variations' => ProductVariation::query()
                ->whereHas('product', fn ($products) => $products->where('shop_id', $shop->id)->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%")))
                ->with(['product:id,name,status', 'inventoryMovements' => fn ($query) => $query->latest('id')->limit(5)])
                ->when($low, fn ($query) => $query->whereRaw('stock - reserved <= 5'))
                ->orderBy('product_id')
                ->orderByDesc('is_default')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (ProductVariation $variation) => [
                    'id' => $variation->id,
                    'product' => $variation->product?->only(['id', 'name']) + ['status' => $variation->product?->status->value],
                    'label' => $variation->label,
                    'sku' => $variation->sku,
                    'stock' => $variation->stock,
                    'reserved' => $variation->reserved,
                    'available' => max(0, $variation->stock - $variation->reserved),
                    'is_active' => $variation->is_active,
                    'movements' => $variation->inventoryMovements->map(fn ($movement) => [
                        'type' => $movement->type->value,
                        'quantity' => $movement->quantity,
                        'balance_after' => $movement->balance_after,
                        'note' => $movement->note,
                        'created_at' => $movement->created_at?->toIso8601String(),
                    ]),
                ]),
            'filters' => ['search' => $search, 'low' => $low],
        ]);
    }

    public function adjustStock(Request $request, ProductVariation $variation): RedirectResponse
    {
        $this->authorizeProduct($request, $variation->product()->withTrashed()->firstOrFail());
        $validated = $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->products->adjustStock($variation, $validated['stock'], $request->user(), $validated['note'] ?? null);

        return back()->with('success', "Stock for {$variation->sku} set to {$validated['stock']}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate(self::rules($creating), self::messages());
    }

    /**
     * Product form rules, shared with the seller API.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $creating): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'brand' => ['nullable', 'string', 'max:100'],
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variations' => ['required', 'array', 'min:1', 'max:30'],
            'variations.*.id' => ['nullable', 'integer'],
            'variations.*.label' => ['required', 'string', 'max:80', 'distinct'],
            'variations.*.price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'variations.*.compare_at_price' => ['nullable', 'numeric', 'gte:variations.*.price', 'max:1000000'],
            'variations.*.stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'variations.*.is_active' => ['boolean'],
            'images' => [$creating ? 'required' : 'nullable', 'array', 'max:'.SellerProductService::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=200,min_height=200,max_width=5000,max_height=5000'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'primary_image_id' => ['nullable', 'integer'],
            'submit' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'variations.*.label.distinct' => 'Each variation needs a different name.',
            'variations.*.compare_at_price.gte' => 'The original price must be at least the selling price.',
            'images.required' => 'Add at least one photo.',
            'images.*.dimensions' => 'Photos must be between 200 and 5000 pixels wide and tall.',
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function categoryOptions(): array
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->flatMap(fn ($parent) => collect([['value' => $parent->id, 'label' => $parent->name]])
                ->concat($parent->children->map(fn ($child) => ['value' => $child->id, 'label' => "{$parent->name} › {$child->name}"])))
            ->values()
            ->all();
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $this->shop($request)->id, 404);
    }
}
