<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ShopCardResource;
use App\Models\Product;
use App\Services\Catalog\Storefront;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private Storefront $storefront) {}

    public function products(Request $request): AnonymousResourceCollection
    {
        return ProductCardResource::collection($this->storefront->search([
            'q' => $request->query('q'),
            'category' => $request->query('category'),
            'shop' => $request->query('shop'),
            'min' => $request->query('min'),
            'max' => $request->query('max'),
            'in_stock' => $request->boolean('in_stock'),
            'sort' => $request->query('sort'),
        ], min(50, max(1, $request->integer('per_page', 24)))));
    }

    public function product(string $slug): ProductDetailResource
    {
        return new ProductDetailResource(
            Product::query()->visible()->where('slug', $slug)->with(['shop', 'category', 'images', 'variations', 'activePromotions'])->firstOrFail()
        );
    }

    public function shops(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q'));

        return ShopCardResource::collection(
            $this->storefront->shopQuery()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderByDesc('completed_orders_count')
                ->paginate(20)
                ->withQueryString()
        );
    }

    public function shop(string $slug): ShopCardResource
    {
        return new ShopCardResource($this->storefront->shopQuery()->where('slug', $slug)->firstOrFail());
    }
}
