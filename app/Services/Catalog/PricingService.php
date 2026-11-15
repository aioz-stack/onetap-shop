<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Support\Money;

/**
 * Applies running shop promotions to list prices. The single source for "what the customer pays per unit".
 * Expects `activePromotions` to be eager-loaded where many products are priced at once.
 */
class PricingService
{
    public function discountPercent(Product $product): string
    {
        $promotion = $product->relationLoaded('activePromotions')
            ? $product->activePromotions->first()
            : $product->activePromotions()->first();

        return $promotion ? Money::format($promotion->discount_percent) : '0.00';
    }

    /**
     * Unit price after the product's best running promotion.
     */
    public function unitPrice(Product $product, string $listPrice): string
    {
        $percent = $this->discountPercent($product);

        if (bccomp($percent, '0', 2) <= 0) {
            return Money::format($listPrice);
        }

        $factor = bcdiv(bcsub('100', $percent, 4), '100', 6);

        return Money::format(round((float) bcmul($listPrice, $factor, 4), 2));
    }
}
