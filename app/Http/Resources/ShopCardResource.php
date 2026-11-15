<?php

namespace App\Http\Resources;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shop
 */
class ShopCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_url' => $this->logo ? asset('storage/'.$this->logo) : null,
            'banner_url' => $this->banner ? asset('storage/'.$this->banner) : null,
            'city' => $this->pickup_city,
            'barangay' => $this->pickup_barangay,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'rating_avg' => (float) $this->rating_avg,
            'products_count' => $this->live_products_count ?? null,
            'completed_orders_count' => $this->completed_orders_count ?? null,
            'vacation_mode' => $this->vacation_mode,
            'vacation_message' => $this->vacation_message,
        ];
    }
}
