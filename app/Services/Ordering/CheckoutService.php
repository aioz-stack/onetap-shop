<?php

namespace App\Services\Ordering;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SellerOrderStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ProductVariation;
use App\Models\User;
use App\Models\UserAddress;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Catalog\PricingService;
use App\Services\Marketing\VoucherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns the selected cart items into one order with one seller order per shop.
 * Everything happens in a single transaction: stock is locked and reserved,
 * the address is snapshotted, commission is fixed at the current rate, and the cart is cleared.
 */
class CheckoutService
{
    public function __construct(
        private InventoryService $inventory,
        private ShippingFeeService $shipping,
        private SettingsService $settings,
        private AuditLogger $auditLogger,
        private PricingService $pricing,
        private VoucherService $vouchers,
    ) {}

    /**
     * @param  array<int|string, string|null>  $voucherCodes  shop id => voucher code
     *
     * @throws ValidationException
     */
    public function placeOrder(User $customer, UserAddress $address, PaymentMethod $method, ?string $notes = null, array $voucherCodes = []): Order
    {
        if ($address->user_id !== $customer->id) {
            throw ValidationException::withMessages(['address_id' => 'Choose one of your saved addresses.']);
        }

        $this->assertPaymentMethodAllowed($method);

        $order = DB::transaction(function () use ($customer, $address, $method, $notes, $voucherCodes) {
            $items = CartItem::query()
                ->whereHas('cart', fn ($carts) => $carts->where('user_id', $customer->id))
                ->where('is_selected', true)
                ->with(['variation.product' => fn ($query) => $query->withTrashed()->with(['shop', 'activePromotions'])])
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Select at least one item to check out.']);
            }

            // Lock every variation in a stable order before checking stock, to avoid deadlocks.
            $variations = ProductVariation::query()
                ->whereIn('id', $items->pluck('product_variation_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->assertItemsPurchasable($items, $variations);

            $rate = number_format((float) $this->settings->get('finance', 'commission_rate', '5'), 2, '.', '');
            $order = Order::query()->create([
                'order_number' => $this->orderNumber(),
                'user_id' => $customer->id,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Pending,
                'items_subtotal' => '0.00',
                'shipping_total' => '0.00',
                'grand_total' => '0.00',
                'notes' => $notes,
                'placed_at' => now(),
            ]);

            $order->address()->create($address->only(['recipient_name', 'phone', 'region', 'province', 'city', 'barangay', 'street', 'postal_code', 'lat', 'lng']));

            $itemsTotal = '0.00';
            $shippingTotal = '0.00';
            $discountTotal = '0.00';
            $sequence = 1;
            $usedVoucherIds = [];
            $unitPrices = $items->mapWithKeys(fn (CartItem $item) => [$item->id => $this->pricing->unitPrice($item->variation->product, $variations[$item->product_variation_id]->price)]);

            foreach ($items->groupBy(fn (CartItem $item) => $item->variation->product->shop_id) as $shopItems) {
                $subtotal = '0.00';

                foreach ($shopItems as $item) {
                    $subtotal = bcadd($subtotal, bcmul($unitPrices[$item->id], (string) $item->quantity, 2), 2);
                }

                $quote = $this->shipping->quote($address->city, $address->barangay, $subtotal);
                $shop = $shopItems->first()->variation->product->shop;
                $code = trim((string) ($voucherCodes[$shop->id] ?? ''));
                $applied = $code !== '' ? $this->vouchers->quote($code, $shop, $subtotal, $quote['fee'], $customer, "vouchers.{$shop->id}") : null;
                $discount = $applied['discount'] ?? '0.00';

                // A voucher counts once per order, even when the same platform code is typed for several shops.
                if ($applied && in_array($applied['voucher']->id, $usedVoucherIds, true)) {
                    throw ValidationException::withMessages(["vouchers.{$shop->id}" => "Voucher {$applied['voucher']->code} can only be used on one shop per order."]);
                }

                if ($applied) {
                    $usedVoucherIds[] = $applied['voucher']->id;
                }

                // Shop vouchers are funded by the seller: commission is charged on the discounted item total and the
                // discount comes out of the seller's earning. Platform vouchers (no shop) are funded by Onetap: the
                // seller is paid as if there were no voucher, and the cost is booked at completion.
                $platformFunded = $applied && $applied['voucher']->shop_id === null;
                $commission = bcdiv(bcmul(bcsub($subtotal, $platformFunded ? '0.00' : ($applied['item_discount'] ?? '0.00'), 2), $rate, 4), '100', 2);

                $sellerOrder = $order->sellerOrders()->create([
                    'shop_id' => $shopItems->first()->variation->product->shop_id,
                    'reference' => $order->order_number.'-'.$sequence++,
                    'status' => $method === PaymentMethod::Cod ? SellerOrderStatus::Pending : SellerOrderStatus::Unpaid,
                    'items_subtotal' => $subtotal,
                    'shipping_fee' => $quote['fee'],
                    'discount' => $discount,
                    'voucher_id' => $applied['voucher']->id ?? null,
                    'total' => bcsub(bcadd($subtotal, $quote['fee'], 2), $discount, 2),
                    'commission_rate' => $rate,
                    'commission_amount' => $commission,
                    'seller_earning' => bcsub(bcsub($subtotal, $commission, 2), $platformFunded ? '0.00' : $discount, 2),
                ]);

                foreach ($shopItems as $item) {
                    $variation = $variations[$item->product_variation_id];
                    $sellerOrder->items()->create([
                        'product_id' => $variation->product_id,
                        'product_variation_id' => $variation->id,
                        'product_name' => $item->variation->product->name,
                        'variation_label' => $variation->label,
                        'unit_price' => $unitPrices[$item->id],
                        'quantity' => $item->quantity,
                        'line_total' => bcmul($unitPrices[$item->id], (string) $item->quantity, 2),
                    ]);

                    $this->inventory->reserve($variation, $item->quantity, $sellerOrder, $customer);
                }

                $sellerOrder->statusHistories()->create([
                    'to_status' => $sellerOrder->status->value,
                    'note' => 'Order placed',
                    'user_id' => $customer->id,
                ]);

                if ($applied) {
                    $this->vouchers->redeem($applied['voucher'], $customer, $order, $discount);
                }

                $itemsTotal = bcadd($itemsTotal, $subtotal, 2);
                $shippingTotal = bcadd($shippingTotal, $quote['fee'], 2);
                $discountTotal = bcadd($discountTotal, $discount, 2);
            }

            $grandTotal = bcsub(bcadd($itemsTotal, $shippingTotal, 2), $discountTotal, 2);
            $this->assertCodLimit($method, $grandTotal);

            $order->forceFill(['items_subtotal' => $itemsTotal, 'shipping_total' => $shippingTotal, 'discount_total' => $discountTotal, 'grand_total' => $grandTotal])->save();
            $order->payments()->create(['method' => $method, 'amount' => $grandTotal, 'status' => PaymentStatus::Pending]);

            CartItem::query()->whereKey($items->pluck('id'))->delete();

            $this->auditLogger->log('order.placed', $order, newValues: ['grand_total' => $grandTotal, 'shops' => $sequence - 1], actor: $customer);

            return $order;
        });

        $order->load('sellerOrders.shop.sellerProfile.user');
        $customer->notify(new OrderPlaced($order));

        foreach ($order->sellerOrders as $sellerOrder) {
            $sellerOrder->shop->sellerProfile?->user?->notify(new NewSellerOrder($sellerOrder));
        }

        return $order;
    }

    private function assertPaymentMethodAllowed(PaymentMethod $method): void
    {
        if ($method !== PaymentMethod::Cod) {
            throw ValidationException::withMessages(['payment_method' => 'Only Cash on Delivery is available right now.']);
        }

        if ($this->settings->get('payment', 'cod_enabled', '1') !== '1') {
            throw ValidationException::withMessages(['payment_method' => 'Cash on Delivery is temporarily unavailable.']);
        }
    }

    private function assertCodLimit(PaymentMethod $method, string $grandTotal): void
    {
        $limit = (string) $this->settings->get('payment', 'cod_max_amount', '0');

        if ($method === PaymentMethod::Cod && bccomp($limit, '0', 2) > 0 && bccomp($grandTotal, $limit, 2) > 0) {
            throw ValidationException::withMessages(['payment_method' => 'Cash on Delivery orders are limited to ₱'.number_format((float) $limit, 2).'. Remove some items and try again.']);
        }
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @param  Collection<int, ProductVariation>  $variations
     */
    private function assertItemsPurchasable($items, $variations): void
    {
        foreach ($items as $item) {
            $variation = $variations[$item->product_variation_id] ?? null;
            $product = $item->variation?->product;
            $name = $product?->name ?? 'An item';

            if (! $variation || ! $variation->is_active || ! $product || $product->trashed() || $product->status->value !== 'live' || $product->shop->status->value !== 'active') {
                throw ValidationException::withMessages(['cart' => "{$name} is no longer available. Remove it to continue."]);
            }

            if ($product->shop->vacation_mode) {
                throw ValidationException::withMessages(['cart' => "{$product->shop->name} is on vacation. Deselect its items to continue."]);
            }

            if ($this->inventory->available($variation) < $item->quantity) {
                throw ValidationException::withMessages(['cart' => "Only {$this->inventory->available($variation)} of {$name} ({$variation->label}) left. Update the quantity to continue."]);
            }
        }
    }

    private function orderNumber(): string
    {
        do {
            $number = 'OT'.now()->format('ymd').Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
