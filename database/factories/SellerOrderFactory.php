<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SellerOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A seller order with a parent order, address and one item whose stock is reserved.
 *
 * @extends Factory<SellerOrder>
 */
class SellerOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => fn () => Order::query()->create([
                'order_number' => 'OT'.now()->format('ymd').Str::upper(Str::random(6)),
                'user_id' => User::factory()->customer()->create()->id,
                'payment_method' => PaymentMethod::Cod,
                'payment_status' => 'pending',
                'items_subtotal' => '300.00',
                'shipping_total' => '59.00',
                'grand_total' => '359.00',
                'placed_at' => now(),
            ])->id,
            'shop_id' => Shop::factory(),
            'reference' => 'SO-'.Str::upper(Str::random(10)),
            'status' => SellerOrderStatus::Pending,
            'items_subtotal' => '300.00',
            'shipping_fee' => '59.00',
            'total' => '359.00',
            'commission_rate' => '5.00',
            'commission_amount' => '15.00',
            'seller_earning' => '285.00',
        ];
    }

    public function status(SellerOrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SellerOrder $sellerOrder) {
            $order = $sellerOrder->order;

            if (! $order->address) {
                $order->address()->create([
                    'recipient_name' => $order->customer->name,
                    'phone' => '09171234567',
                    'region' => 'NCR',
                    'province' => 'Metro Manila',
                    'city' => 'Quezon City',
                    'barangay' => 'Bagong Pag-asa',
                    'street' => '12 Mabini St.',
                ]);
            }

            $product = Product::factory()->create(['shop_id' => $sellerOrder->shop_id, 'base_price' => '150.00']);
            $variation = $product->variations()->first();

            $sellerOrder->items()->create([
                'product_id' => $product->id,
                'product_variation_id' => $variation->id,
                'product_name' => $product->name,
                'variation_label' => $variation->label,
                'unit_price' => '150.00',
                'quantity' => 2,
                'line_total' => '300.00',
            ]);

            if ($sellerOrder->status->holdsReservedStock()) {
                $variation->increment('reserved', 2);
            }

            $sellerOrder->statusHistories()->create(['to_status' => $sellerOrder->status->value]);
        });
    }
}
