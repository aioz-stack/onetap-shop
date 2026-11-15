<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Models\Refund;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A refund request on a delivered order that has moved to return_requested.
 *
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_order_id' => SellerOrder::factory()->status(SellerOrderStatus::ReturnRequested),
            'user_id' => fn (array $attributes) => SellerOrder::find($attributes['seller_order_id'])->order->user_id,
            'reason' => 'Item arrived damaged',
            'details' => 'The box was crushed.',
            'amount' => '300.00',
            'status' => RefundStatus::Requested,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Refund $refund) {
            $refund->sellerOrder->statusHistories()->create([
                'from_status' => SellerOrderStatus::Delivered->value,
                'to_status' => SellerOrderStatus::ReturnRequested->value,
            ]);
        });
    }
}
