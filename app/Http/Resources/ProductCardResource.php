<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\Catalog\PricingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact product for grids and lists. Expects `min_price`, `max_price` and
 * `available_stock` aggregates plus `shop` and `primaryImage` to be loaded
 * (see Storefront::cardQuery()).
 *
 * @mixin Product
 */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricing = app(PricingService::class);
        $minList = Money::format($this->min_price ?? $this->base_price);
        $maxList = Money::format($this->max_price ?? $this->min_price ?? $this->base_price);
        $percent = $this->relationLoaded('activePromotions') ? $pricing->discountPercent($this->resource) : '0.00';
        $onPromotion = bccomp($percent, '0', 2) > 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $onPromotion ? $pricing->unitPrice($this->resource, $minList) : $minList,
            'max_price' => $onPromotion ? $pricing->unitPrice($this->resource, $maxList) : $maxList,
            'compare_at_price' => $onPromotion ? $minList : ($this->max_compare_price ? Money::format($this->max_compare_price) : null),
            'discount_percent' => $onPromotion ? (float) $percent : null,
            'image_url' => $this->primaryImage ? asset('storage/'.$this->primaryImage->path) : null,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'sold_count' => $this->sold_count,
            'in_stock' => (int) ($this->available_stock ?? 0) > 0,
            'shop' => $this->whenLoaded('shop', fn () => ['id' => $this->shop->id, 'name' => $this->shop->name, 'slug' => $this->shop->slug, 'city' => $this->shop->pickup_city]),
        ];
    }
}
