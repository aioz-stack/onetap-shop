<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ShopCardResource;
use App\Services\Catalog\Storefront;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Home extends Controller
{
    public function __construct(private Storefront $storefront) {}

    public function index(Request $request): Response
    {
        $sections = $this->storefront->homeSections($request->user());

        return Inertia::render('Customer/Home', [
            'categories' => $this->storefront->categoryTree(),
            'banners' => $sections['banners']->map(fn ($banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'image_url' => $banner->image ? asset('storage/'.$banner->image) : null,
                'link_url' => $banner->link_url,
            ]),
            'featured' => ProductCardResource::collection($sections['featured'])->resolve(),
            'recommended' => ProductCardResource::collection($sections['recommended'])->resolve(),
            'bestSellers' => ProductCardResource::collection($sections['bestSellers'])->resolve(),
            'newArrivals' => ProductCardResource::collection($sections['newArrivals'])->resolve(),
            'popularShops' => ShopCardResource::collection($sections['popularShops'])->resolve(),
        ]);
    }
}
