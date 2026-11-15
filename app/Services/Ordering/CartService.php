<?php

namespace App\Services\Ordering;

use App\Models\CartItem;
use App\Models\ProductVariation;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Catalog\PricingService;
use App\Services\Marketing\VoucherService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The customer's cart, grouped by shop with live prices, stock and shipping quotes.
 */
class CartService
{
    public const MAX_QUANTITY = 99;

    public function __construct(
        private InventoryService $inventory,
        private ShippingFeeService $shipping,
        private PricingService $pricing,
        private VoucherService $vouchers,
    ) {}

    public function cartFor(User $user): ShoppingCart
    {
        return $user->cart()->firstOrCreate([]);
    }

    public function add(User $user, ProductVariation $variation, int $quantity): CartItem
    {
        $this->assertPurchasable($variation, $user);
        $cart = $this->cartFor($user);
        $item = $cart->items()->firstOrNew(['product_variation_id' => $variation->id]);
        $newQuantity = min(self::MAX_QUANTITY, ($item->exists ? $item->quantity : 0) + max(1, $quantity));

        $this->assertAvailable($variation, $newQuantity);

        $item->fill(['quantity' => $newQuantity, 'is_selected' => true])->save();

        return $item;
    }

    public function update(CartItem $item, ?int $quantity, ?bool $selected): CartItem
    {
        if ($quantity !== null) {
            $quantity = max(1, min(self::MAX_QUANTITY, $quantity));
            $this->assertAvailable($item->variation, $quantity);
            $item->quantity = $quantity;
        }

        if ($selected !== null) {
            $item->is_selected = $selected;
        }

        $item->save();

        return $item;
    }

    /**
     * Select or deselect every item, or every item of one shop.
     */
    public function selectAll(User $user, bool $selected, ?int $shopId = null): void
    {
        $this->cartFor($user)->items()
            ->when($shopId, fn ($query) => $query->whereHas('variation.product', fn ($products) => $products->where('shop_id', $shopId)))
            ->update(['is_selected' => $selected]);
    }

    public function count(User $user): int
    {
        return (int) $user->cart?->items()->sum('quantity');
    }

    /**
     * The cart grouped by shop, with line totals, problems and per-shop shipping quotes.
     *
     * @return array{groups: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    /**
     * @param  array<int|string, string|null>  $vouchers  shop id => voucher code
     */
    public function summarize(User $user, ?UserAddress $address = null, bool $selectedOnly = false, array $vouchers = []): array
    {
        $items = $this->cartFor($user)->items()
            ->with(['variation.product' => fn ($query) => $query->withTrashed()->with(['shop', 'primaryImage', 'activePromotions'])])
            ->latest('id')
            ->get()
            ->filter(fn (CartItem $item) => $item->variation && $item->variation->product)
            ->when($selectedOnly, fn (Collection $collection) => $collection->where('is_selected', true));

        $address ??= $user->addresses()->orderByDesc('is_default')->first();
        $groups = [];
        $totals = ['items' => '0.00', 'shipping' => '0.00', 'discount' => '0.00', 'quantity' => 0, 'selected' => 0];

        foreach ($items->groupBy(fn (CartItem $item) => $item->variation->product->shop_id) as $shopItems) {
            $shop = $shopItems->first()->variation->product->shop;
            $lines = [];
            $subtotal = '0.00';

            foreach ($shopItems as $item) {
                $variation = $item->variation;
                $product = $variation->product;
                $available = $this->inventory->available($variation);
                $problem = $this->problemFor($item, $available);
                $unitPrice = $this->pricing->unitPrice($product, $variation->price);
                $lineTotal = bcmul($unitPrice, (string) $item->quantity, 2);

                if ($item->is_selected && ! $problem) {
                    $subtotal = bcadd($subtotal, $lineTotal, 2);
                    $totals['quantity'] += $item->quantity;
                    $totals['selected']++;
                }

                $lines[] = [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'is_selected' => $item->is_selected,
                    'available' => $available,
                    'problem' => $problem,
                    'unit_price' => $unitPrice,
                    'list_price' => $variation->price,
                    'compare_at_price' => $variation->compare_at_price,
                    'line_total' => $lineTotal,
                    'variation' => ['id' => $variation->id, 'label' => $variation->label, 'sku' => $variation->sku],
                    'product' => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'image' => $product->primaryImage?->path],
                ];
            }

            $hasSelection = bccomp($subtotal, '0', 2) > 0;
            $quote = $hasSelection ? $this->shipping->quote($address?->city, $address?->barangay, $subtotal) : null;

            if ($quote) {
                $totals['shipping'] = bcadd($totals['shipping'], $quote['fee'], 2);
            }

            [$voucher, $voucherError] = [null, null];
            $code = trim((string) ($vouchers[$shop->id] ?? ''));

            if ($code !== '' && $quote) {
                try {
                    $applied = $this->vouchers->quote($code, $shop, $subtotal, $quote['fee'], $user);
                    $voucher = ['code' => $applied['voucher']->code, 'label' => $applied['label'], 'discount' => $applied['discount']];
                    $totals['discount'] = bcadd($totals['discount'], $applied['discount'], 2);
                } catch (ValidationException $exception) {
                    $voucherError = collect($exception->errors())->flatten()->first();
                }
            }

            $totals['items'] = bcadd($totals['items'], $subtotal, 2);

            $groups[] = [
                'shop' => ['id' => $shop->id, 'name' => $shop->name, 'slug' => $shop->slug, 'vacation_mode' => $shop->vacation_mode],
                'items' => $lines,
                'subtotal' => $subtotal,
                'shipping' => $quote,
                'voucher' => $voucher,
                'voucher_code' => $code ?: null,
                'voucher_error' => $voucherError,
                'total' => Money::format(bcsub(bcadd($subtotal, $quote['fee'] ?? '0', 2), $voucher['discount'] ?? '0', 2)),
            ];
        }

        return [
            'groups' => $groups,
            'summary' => [
                'items_subtotal' => $totals['items'],
                'shipping_total' => $totals['shipping'],
                'discount_total' => $totals['discount'],
                'grand_total' => bcsub(bcadd($totals['items'], $totals['shipping'], 2), $totals['discount'], 2),
                'selected_lines' => $totals['selected'],
                'selected_quantity' => $totals['quantity'],
                'address_id' => $address?->id,
            ],
        ];
    }

    private function problemFor(CartItem $item, int $available): ?string
    {
        $variation = $item->variation;
        $product = $variation->product;

        return match (true) {
            ! $variation->is_active || $product->status->value !== 'live' || $product->trashed() => 'No longer available',
            $product->shop->status->value !== 'active' => 'Shop is not accepting orders',
            $product->shop->vacation_mode => 'Shop is on vacation',
            $available < 1 => 'Out of stock',
            $item->quantity > $available => "Only {$available} left",
            default => null,
        };
    }

    private function assertPurchasable(ProductVariation $variation, User $user): void
    {
        $product = $variation->product()->withTrashed()->with('shop.sellerProfile')->first();

        if ($product?->shop?->sellerProfile?->user_id === $user->id) {
            throw ValidationException::withMessages(['variation_id' => 'You cannot buy from your own shop.']);
        }

        if (! $variation->is_active || ! $product || $product->trashed() || $product->status->value !== 'live' || $product->shop?->status->value !== 'active') {
            throw ValidationException::withMessages(['variation_id' => 'This item is not available.']);
        }

        if ($product->shop->vacation_mode) {
            throw ValidationException::withMessages(['variation_id' => "{$product->shop->name} is on vacation and not taking orders."]);
        }
    }

    private function assertAvailable(ProductVariation $variation, int $quantity): void
    {
        $available = $this->inventory->available($variation->fresh());

        if ($quantity > $available) {
            throw ValidationException::withMessages(['quantity' => $available > 0 ? "Only {$available} left in stock." : 'This item is out of stock.']);
        }
    }
}
