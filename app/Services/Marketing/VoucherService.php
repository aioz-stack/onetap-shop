<?php

namespace App\Services\Marketing;

use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Shop vouchers: validation, discount calculation and redemption.
 * The seller funds the discount; free-shipping vouchers cover the delivery fee.
 */
class VoucherService
{
    /**
     * @return array{voucher: Voucher, discount: string, item_discount: string, label: string}
     *
     * @throws ValidationException
     */
    public function quote(string $code, Shop $shop, string $subtotal, string $shippingFee, User $customer, string $errorKey = 'voucher'): array
    {
        $voucher = Voucher::query()->whereRaw('upper(code) = ?', [strtoupper(trim($code))])->first();
        $fail = fn (string $message) => throw ValidationException::withMessages([$errorKey => $message]);

        if (! $voucher || ! $voucher->is_active || ($voucher->shop_id && $voucher->shop_id !== $shop->id)) {
            $fail("“{$code}” is not a valid voucher for {$shop->name}.");
        }

        if (($voucher->starts_at && $voucher->starts_at->isFuture()) || ($voucher->ends_at && $voucher->ends_at->isPast())) {
            $fail("Voucher {$voucher->code} is not active right now.");
        }

        if (bccomp($subtotal, $voucher->min_spend, 2) < 0) {
            $fail("Voucher {$voucher->code} needs a minimum spend of ₱".number_format((float) $voucher->min_spend, 2).'.');
        }

        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) {
            $fail("Voucher {$voucher->code} has been fully claimed.");
        }

        $used = VoucherRedemption::query()->where('voucher_id', $voucher->id)->where('user_id', $customer->id)->count();

        if ($used >= $voucher->per_user_limit) {
            $fail("You have already used voucher {$voucher->code}.");
        }

        [$discount, $itemDiscount] = match ($voucher->type) {
            VoucherType::Fixed => [$this->min($voucher->value, $subtotal), $this->min($voucher->value, $subtotal)],
            VoucherType::Percent => (function () use ($voucher, $subtotal) {
                $amount = Money::format(round((float) bcdiv(bcmul($subtotal, $voucher->value, 4), '100', 4), 2));
                $amount = $voucher->max_discount ? $this->min($amount, $voucher->max_discount) : $amount;

                return [$amount, $amount];
            })(),
            VoucherType::FreeShipping => [$this->min($voucher->max_discount ?? $shippingFee, $shippingFee), '0.00'],
        };

        return [
            'voucher' => $voucher,
            'discount' => $discount,
            'item_discount' => $itemDiscount,
            'label' => $this->describe($voucher),
        ];
    }

    /**
     * Record the use and bump the counter. Call inside the checkout transaction.
     */
    public function redeem(Voucher $voucher, User $customer, Order $order, string $amount): void
    {
        $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);

        if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
            throw ValidationException::withMessages(['voucher' => "Voucher {$locked->code} was just fully claimed."]);
        }

        $locked->increment('used_count');
        VoucherRedemption::query()->create(['voucher_id' => $locked->id, 'user_id' => $customer->id, 'order_id' => $order->id, 'amount' => $amount]);
    }

    public function describe(Voucher $voucher): string
    {
        return match ($voucher->type) {
            VoucherType::Fixed => '₱'.number_format((float) $voucher->value, 2).' off',
            VoucherType::Percent => rtrim(rtrim(number_format((float) $voucher->value, 2), '0'), '.').'% off'.($voucher->max_discount ? ' (up to ₱'.number_format((float) $voucher->max_discount, 2).')' : ''),
            VoucherType::FreeShipping => 'Free delivery',
        };
    }

    private function min(string $a, string $b): string
    {
        return Money::format(bccomp($a, $b, 2) <= 0 ? $a : $b);
    }
}
