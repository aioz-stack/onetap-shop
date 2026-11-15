<?php

namespace App\Services\Catalog;

use App\Enums\SellerOrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verified-purchase reviews: one per order item, only after the order completes.
 * Product and shop rating aggregates are recalculated on every change.
 */
class ReviewService
{
    public function create(OrderItem $item, User $customer, int $rating, ?string $comment): ProductReview
    {
        $item->loadMissing('sellerOrder.order');

        abort_unless($item->sellerOrder->order->user_id === $customer->id, 404);

        if ($item->sellerOrder->status !== SellerOrderStatus::Completed) {
            throw ValidationException::withMessages(['rating' => 'You can review items after you confirm the order was received.']);
        }

        if ($item->review()->exists()) {
            throw ValidationException::withMessages(['rating' => 'You already reviewed this item.']);
        }

        return DB::transaction(function () use ($item, $customer, $rating, $comment) {
            $review = ProductReview::query()->create([
                'product_id' => $item->product_id,
                'order_item_id' => $item->id,
                'user_id' => $customer->id,
                'rating' => max(1, min(5, $rating)),
                'comment' => $comment,
                'is_visible' => true,
            ]);

            $this->refreshAggregates($item->product_id);

            return $review;
        });
    }

    public function refreshAggregates(int $productId): void
    {
        $product = Product::withTrashed()->findOrFail($productId);
        $visible = ProductReview::query()->where('product_id', $productId)->where('is_visible', true);

        $product->forceFill([
            'rating_count' => (clone $visible)->count(),
            'rating_avg' => round((float) (clone $visible)->avg('rating'), 2),
        ])->save();

        $shopAverage = ProductReview::query()
            ->where('is_visible', true)
            ->whereIn('product_id', Product::withTrashed()->where('shop_id', $product->shop_id)->select('id'))
            ->avg('rating');

        Shop::query()->whereKey($product->shop_id)->update(['rating_avg' => round((float) $shopAverage, 2)]);
    }
    public function reply(ProductReview $review, Shop $shop, string $reply): ProductReview
    {
        abort_unless($review->product()->withTrashed()->value('shop_id') === $shop->id, 404);

        $review->forceFill(['seller_reply' => trim($reply), 'replied_at' => now()])->save();

        return $review;
    }
}
