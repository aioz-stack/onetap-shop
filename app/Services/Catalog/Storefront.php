<?php

namespace App\Services\Catalog;

use App\Enums\SellerOrderStatus;
use App\Enums\ShopStatus;
use App\Models\Banner;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-side queries for the public storefront, shared by the web pages and the API.
 */
class Storefront
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc', 'best_selling', 'top_rated'];

    /**
     * Visible products with what product cards need.
     *
     * @return Builder<Product>
     */
    public function cardQuery(): Builder
    {
        return Product::query()
            ->visible()
            ->with(['shop:id,name,slug,pickup_city', 'primaryImage', 'activePromotions'])
            ->withMin(['variations as min_price' => fn ($query) => $query->where('is_active', true)], 'price')
            ->withMax(['variations as max_price' => fn ($query) => $query->where('is_active', true)], 'price')
            ->withMax(['variations as max_compare_price' => fn ($query) => $query->where('is_active', true)], 'compare_at_price')
            ->withSum(['variations as available_stock' => fn ($query) => $query->where('is_active', true)], DB::raw('stock - reserved'));
    }

    /**
     * @return array<string, mixed>
     */
    public function homeSections(?User $user): array
    {
        $recommendedCategories = $user
            ? OrderItem::query()
                ->whereHas('sellerOrder.order', fn ($orders) => $orders->where('user_id', $user->id))
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->pluck('products.product_category_id')
                ->filter()
                ->unique()
                ->values()
            : collect();

        $recommended = $this->cardQuery()
            ->when($recommendedCategories->isNotEmpty(), fn ($query) => $query->whereIn('product_category_id', $recommendedCategories))
            ->inRandomOrder()
            ->limit(8)
            ->get();

        return [
            'banners' => Banner::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderBy('sort_order')
                ->get(['id', 'title', 'subtitle', 'image', 'link_url']),
            'featured' => $this->cardQuery()->where('rating_count', '>', 0)->orderByDesc('rating_avg')->orderByDesc('rating_count')->limit(8)->get(),
            'recommended' => $recommended,
            'bestSellers' => $this->cardQuery()->where('sold_count', '>', 0)->orderByDesc('sold_count')->limit(8)->get(),
            'newArrivals' => $this->cardQuery()->orderByDesc('published_at')->orderByDesc('id')->limit(8)->get(),
            'popularShops' => $this->shopQuery()->orderByDesc('completed_orders_count')->orderByDesc('rating_avg')->limit(6)->get(),
        ];
    }

    /**
     * @return Builder<Shop>
     */
    public function shopQuery(): Builder
    {
        return Shop::query()
            ->where('status', ShopStatus::Active)
            ->with('category:id,name')
            ->withCount([
                'products as live_products_count' => fn ($query) => $query->visible(),
                'sellerOrders as completed_orders_count' => fn ($query) => $query->where('status', SellerOrderStatus::Completed),
            ]);
    }

    /**
     * @param  array{q?: string|null, category?: string|null, shop?: string|null, min?: numeric-string|null, max?: numeric-string|null, in_stock?: bool|null, sort?: string|null}  $filters
     */
    public function search(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $term = trim((string) ($filters['q'] ?? ''));
        $category = filled($filters['category'] ?? null) ? ProductCategory::query()->where('slug', $filters['category'])->first() : null;
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : ($term !== '' ? 'relevance' : 'newest');

        $query = $this->cardQuery()
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.brand', 'like', "%{$term}%")
                ->orWhere('products.description', 'like', "%{$term}%")
                ->orWhereHas('shop', fn ($shops) => $shops->where('name', 'like', "%{$term}%"))))
            ->when($category, fn ($query) => $query->whereIn('product_category_id', ProductCategory::query()->where('id', $category->id)->orWhere('parent_id', $category->id)->select('id')))
            ->when(filled($filters['shop'] ?? null), fn ($query) => $query->whereHas('shop', fn ($shops) => $shops->where('slug', $filters['shop'])))
            ->when(is_numeric($filters['min'] ?? null), fn ($query) => $query->whereRaw(self::effectivePriceSql().' >= cast(? as decimal(14,2))', [...self::effectivePriceBindings(), (float) $filters['min']]))
            ->when(is_numeric($filters['max'] ?? null), fn ($query) => $query->whereRaw(self::effectivePriceSql().' <= cast(? as decimal(14,2))', [...self::effectivePriceBindings(), (float) $filters['max']]))
            ->when($filters['in_stock'] ?? false, fn ($query) => $query->whereHas('variations', fn ($variations) => $variations->where('is_active', true)->whereColumn('stock', '>', 'reserved')));

        match ($sort) {
            'price_asc' => $query->orderByRaw(self::effectivePriceSql().' asc', self::effectivePriceBindings()),
            'price_desc' => $query->orderByRaw(self::effectivePriceSql().' desc', self::effectivePriceBindings()),
            'best_selling' => $query->orderByDesc('sold_count'),
            'top_rated' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            'relevance' => $query->orderByRaw('case when products.name like ? then 0 else 1 end', ["%{$term}%"])->orderByDesc('sold_count'),
            default => $query->orderByDesc('published_at')->orderByDesc('products.id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    public function categoryTree(): Collection
    {
        return ProductCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon', 'parent_id']);
    }

    /**
     * The "from" price a shopper sees: the cheapest active variation after the product's best running
     * promotion. Used for price filters and sorting so they match the prices on the cards.
     */
    public static function effectivePriceSql(): string
    {
        return '((select min(pv.price) from product_variations pv where pv.product_id = products.id and pv.is_active = 1)'
            .' * (100 - coalesce((select max(pr.discount_percent) from promotions pr'
            .' inner join promotion_products pp on pp.promotion_id = pr.id'
            .' where pp.product_id = products.id and pr.is_active = 1'
            .' and (pr.starts_at is null or pr.starts_at <= ?) and (pr.ends_at is null or pr.ends_at >= ?)), 0)) / 100)';
    }

    /**
     * @return list<string>
     */
    public static function effectivePriceBindings(): array
    {
        $now = now()->toDateTimeString();

        return [$now, $now];
    }
}
