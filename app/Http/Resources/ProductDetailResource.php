<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\Catalog\PricingService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variations = $this->variations->where('is_active', true)->values();
        $pricing = app(PricingService::class);
        $percent = $pricing->discountPercent($this->resource);
        $onPromotion = bccomp($percent, '0', 2) > 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'brand' => $this->brand,
            'weight_grams' => $this->weight_grams,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'sold_count' => $this->sold_count,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null,
            'images' => $this->images->sortBy([['is_primary', 'desc'], ['sort_order', 'asc']])->values()->map(fn ($image) => [
                'id' => $image->id,
                'url' => asset('storage/'.$image->path),
            ]),
            'variations' => $variations->map(fn ($variation) => [
                'id' => $variation->id,
                'label' => $variation->label,
                'options' => $variation->options,
                'price' => $pricing->unitPrice($this->resource, $variation->price),
                'compare_at_price' => $onPromotion ? Money::format($variation->compare_at_price && bccomp($variation->compare_at_price, $variation->price, 2) > 0 ? $variation->compare_at_price : $variation->price) : ($variation->compare_at_price ? Money::format($variation->compare_at_price) : null),
                'available' => max(0, $variation->stock - $variation->reserved),
                'is_default' => $variation->is_default,
            ]),
            'discount_percent' => $onPromotion ? (float) $percent : null,
            'promotion' => $onPromotion ? $this->activePromotions->first()?->only(['name', 'ends_at']) : null,
            'price_range' => [
                'min' => $pricing->unitPrice($this->resource, Money::format($variations->min('price') ?? $this->base_price)),
                'max' => $pricing->unitPrice($this->resource, Money::format($variations->max('price') ?? $this->base_price)),
            ],
            'shop' => [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'slug' => $this->shop->slug,
                'logo_url' => $this->shop->logo ? asset('storage/'.$this->shop->logo) : null,
                'city' => $this->shop->pickup_city,
                'rating_avg' => (float) $this->shop->rating_avg,
                'vacation_mode' => $this->shop->vacation_mode,
                'vacation_message' => $this->shop->vacation_message,
                'joined_at' => $this->shop->created_at?->toIso8601String(),
            ],
        ];
    }
}
