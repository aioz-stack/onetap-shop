<?php

namespace App\Http\Controllers\Customer;

use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ShopCardResource;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Services\Catalog\Storefront;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Search results, product pages and shop pages.
 */
class Catalog extends Controller
{
    public function __construct(private Storefront $storefront) {}

    public function search(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q')),
            'category' => $request->query('category'),
            'shop' => $request->query('shop'),
            'min' => $request->query('min'),
            'max' => $request->query('max'),
            'in_stock' => $request->boolean('in_stock'),
            'sort' => $request->query('sort'),
        ];

        $products = $this->storefront->search($filters);
        $shops = $filters['q'] !== ''
            ? $this->storefront->shopQuery()->where('name', 'like', "%{$filters['q']}%")->limit(4)->get()
            : collect();

        return Inertia::render('Customer/Search', [
            'products' => ProductCardResource::collection($products)->response()->getData(true),
            'shops' => ShopCardResource::collection($shops)->resolve(),
            'filters' => $filters,
            'categories' => $this->storefront->categoryTree(),
            'sorts' => Storefront::SORTS,
        ]);
    }

    public function product(Request $request, string $slug): Response
    {
        $product = Product::query()
            ->visible()
            ->where('slug', $slug)
            ->with(['shop', 'category', 'images', 'variations', 'activePromotions'])
            ->firstOrFail();

        $reviews = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_visible', true)
            ->with(['user:id,name,profile_picture', 'orderItem:id,variation_label'])
            ->latest('id')
            ->paginate(5, ['*'], 'reviews')
            ->withQueryString();

        $distribution = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_visible', true)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return Inertia::render('Customer/Product', [
            'product' => (new ProductDetailResource($product))->resolve(),
            'reviews' => [
                'data' => $reviews->getCollection()->map(fn (ProductReview $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'variation' => $review->orderItem?->variation_label,
                    'author' => $this->maskName($review->user?->name),
                    'seller_reply' => $review->seller_reply,
                    'created_at' => $review->created_at?->toIso8601String(),
                ]),
                'meta' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'total' => $reviews->total()],
                'links' => $reviews->linkCollection(),
                'distribution' => collect(range(5, 1))->mapWithKeys(fn ($star) => [$star => (int) ($distribution[$star] ?? 0)]),
            ],
            'related' => ProductCardResource::collection(
                $this->storefront->cardQuery()
                    ->where('products.id', '!=', $product->id)
                    ->where(fn ($query) => $query->where('product_category_id', $product->product_category_id)->orWhere('shop_id', $product->shop_id))
                    ->inRandomOrder()
                    ->limit(6)
                    ->get()
            )->resolve(),
            'shopStats' => [
                'products' => $product->shop->products()->visible()->count(),
                'orders_completed' => $product->shop->sellerOrders()->where('status', SellerOrderStatus::Completed)->count(),
                'ratings' => (int) $product->shop->products()->sum('rating_count'),
            ],
            // A seller can't chat with their own shop; the page links to the Seller Center instead.
            'isOwnShop' => $request->user() !== null && $product->shop->sellerProfile()->value('user_id') === $request->user()->id,
        ]);
    }

    public function shop(Request $request, string $slug): Response
    {
        $shop = $this->storefront->shopQuery()->where('slug', $slug)->firstOrFail();
        $products = $this->storefront->search([
            'shop' => $shop->slug,
            'q' => $request->query('q'),
            'sort' => $request->query('sort', 'best_selling'),
        ]);

        return Inertia::render('Customer/Shop', [
            'shop' => (new ShopCardResource($shop))->resolve() + [
                'joined_at' => $shop->created_at?->toIso8601String(),
                'template' => $shop->template,
                'decoration' => $shop->decoration ?? [],
            ],
            'products' => ProductCardResource::collection($products)->response()->getData(true),
            'filters' => ['q' => $request->query('q'), 'sort' => $request->query('sort', 'best_selling')],
            'sorts' => Storefront::SORTS,
        ]);
    }

    /**
     * "Juan Dela Cruz" → "Juan D." for public reviews.
     */
    private function maskName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        if (! $parts || $parts[0] === '') {
            return 'Customer';
        }

        return count($parts) > 1 ? $parts[0].' '.mb_substr(end($parts), 0, 1).'.' : $parts[0];
    }
}
