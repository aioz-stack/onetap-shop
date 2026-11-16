<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ShopCardResource;
use App\Models\OrderItem;
use App\Services\Catalog\Storefront;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer app screens that have no other home: the home feed and "My reviews".
 */
class CustomerAppController extends Controller
{
    public function __construct(private Storefront $storefront) {}

    /**
     * Home screen in one call: banners, categories and product rails. Public; signed-in customers
     * get recommendations based on what they bought (send the Bearer token when you have one).
     */
    public function home(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $sections = $this->storefront->homeSections($user);

        return response()->json(['data' => [
            'banners' => $sections['banners']->map(fn ($banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'image_url' => $banner->image ? asset('storage/'.$banner->image) : null,
                'link_url' => $banner->link_url,
            ])->values(),
            'categories' => $this->storefront->categoryTree()->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'children' => $category->children->map->only(['id', 'name', 'slug'])->values(),
            ])->values(),
            'featured' => ProductCardResource::collection($sections['featured'])->resolve(),
            'recommended' => ProductCardResource::collection($sections['recommended'])->resolve(),
            'best_sellers' => ProductCardResource::collection($sections['bestSellers'])->resolve(),
            'new_arrivals' => ProductCardResource::collection($sections['newArrivals'])->resolve(),
            'popular_shops' => ShopCardResource::collection($sections['popularShops'])->resolve(),
        ]]);
    }

    /**
     * Items from completed orders still waiting for a review.
     */
    public function pendingReviews(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = OrderItem::query()
            ->whereHas('sellerOrder', fn ($orders) => $orders->where('status', SellerOrderStatus::Completed)->whereHas('order', fn ($inner) => $inner->where('user_id', $user->id)))
            ->doesntHave('review')
            ->with(['product' => fn ($query) => $query->withTrashed()->with('primaryImage'), 'sellerOrder:id,reference,completed_at'])
            ->latest('id')
            ->get();

        return response()->json(['data' => $items->map(fn (OrderItem $item) => [
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'variation_label' => $item->variation_label,
            'image_url' => $item->product?->primaryImage ? asset('storage/'.$item->product->primaryImage->path) : null,
            'order_id' => $item->seller_order_id,
            'order_reference' => $item->sellerOrder?->reference,
            'completed_at' => $item->sellerOrder?->completed_at?->toIso8601String(),
        ])->values()]);
    }

    public function myReviews(Request $request): JsonResponse
    {
        $page = $request->user()->productReviews()
            ->with(['product' => fn ($query) => $query->withTrashed()->select('id', 'name', 'slug')->with('primaryImage')])
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'seller_reply' => $review->seller_reply,
                'product' => $review->product ? $review->product->only(['id', 'name', 'slug']) + ['image_url' => $review->product->primaryImage ? asset('storage/'.$review->product->primaryImage->path) : null] : null,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
