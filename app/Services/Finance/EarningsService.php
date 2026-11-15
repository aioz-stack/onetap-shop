<?php

namespace App\Services\Finance;

use App\Enums\SellerOrderStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Commission;
use App\Models\SellerOrder;
use App\Services\Administration\SettingsService;
use Illuminate\Support\Facades\DB;

/**
 * Money postings when a seller order completes, and the later release of the hold.
 */
class EarningsService
{
    public function __construct(private LedgerService $ledger, private SettingsService $settings) {}

    /**
     * Record commission and credit the seller's pending balance. Safe to call twice.
     */
    public function postCompletion(SellerOrder $order): void
    {
        DB::transaction(function () use ($order) {
            if (Commission::query()->where('seller_order_id', $order->id)->exists()) {
                return;
            }

            $order->loadMissing('shop');

            Commission::query()->create([
                'seller_order_id' => $order->id,
                'rate' => $order->commission_rate,
                'base_amount' => $order->items_subtotal,
                'amount' => $order->commission_amount,
            ]);

            if (bccomp($order->commission_amount, '0', 2) > 0) {
                $this->ledger->record(TransactionType::Commission, TransactionDirection::Credit, $order->commission_amount, $order, [
                    'description' => "Commission on {$order->reference}",
                ]);
            }

            if (bccomp($order->seller_earning, '0', 2) > 0) {
                $balance = $this->ledger->creditPending($order->shop, $order->seller_earning);
                $this->ledger->record(TransactionType::SaleEarning, TransactionDirection::Credit, $order->seller_earning, $order, [
                    'shop' => $order->shop,
                    'balance_after' => $balance->available_balance,
                    'description' => "Earning from {$order->reference} (pending)",
                ]);
            }
        });
    }

    /**
     * Move earnings older than the hold period from pending to available. Returns how many orders were released.
     */
    public function releaseDue(): int
    {
        $holdDays = (int) $this->settings->get('finance', 'earnings_hold_days', 3);
        $released = 0;

        SellerOrder::query()
            ->where('status', SellerOrderStatus::Completed)
            ->whereNull('earnings_released_at')
            ->where('completed_at', '<=', now()->subDays($holdDays))
            ->whereHas('commission')
            ->with('shop')
            ->chunkById(100, function ($orders) use (&$released) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, &$released) {
                        $locked = SellerOrder::query()->lockForUpdate()->find($order->id);

                        if ($locked->earnings_released_at) {
                            return;
                        }

                        $balance = $this->ledger->balanceFor($order->shop);
                        $amount = bccomp($balance->pending_balance, $locked->seller_earning, 2) >= 0 ? $locked->seller_earning : $balance->pending_balance;
                        $balance->pending_balance = bcsub($balance->pending_balance, $amount, 2);
                        $balance->available_balance = bcadd($balance->available_balance, $amount, 2);
                        $balance->save();

                        $locked->forceFill(['earnings_released_at' => now()])->save();
                        $released++;
                    });
                }
            });

        return $released;
    }
}
