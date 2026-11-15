<?php

namespace App\Services\Finance;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\SellerBalance;
use App\Models\Shop;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Append-only money ledger plus seller balance bookkeeping.
 * Amounts are handled as decimal strings with bcmath so no float rounding creeps in.
 * Callers must already be inside a DB transaction.
 */
class LedgerService
{
    /**
     * @param  array{shop?: Shop|null, user_id?: int|null, logistics_partner_id?: int|null, balance_after?: string|null, description?: string|null}  $parties
     */
    public function record(TransactionType $type, TransactionDirection $direction, string $amount, ?Model $reference = null, array $parties = []): Transaction
    {
        $this->assertPositive($amount);

        return Transaction::query()->create([
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'shop_id' => ($parties['shop'] ?? null)?->id,
            'user_id' => $parties['user_id'] ?? null,
            'logistics_partner_id' => $parties['logistics_partner_id'] ?? null,
            'balance_after' => $parties['balance_after'] ?? null,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $parties['description'] ?? null,
        ]);
    }

    public function balanceFor(Shop $shop): SellerBalance
    {
        SellerBalance::query()->firstOrCreate(['shop_id' => $shop->id]);

        return SellerBalance::query()->where('shop_id', $shop->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Move money out of the seller's available balance (withdrawal hold, refund clawback).
     */
    public function debitAvailable(Shop $shop, string $amount): SellerBalance
    {
        $this->assertPositive($amount);
        $balance = $this->balanceFor($shop);

        if (bccomp($balance->available_balance, $amount, 2) < 0) {
            throw new InvalidArgumentException('The amount is more than the available balance.');
        }

        $balance->available_balance = bcsub($balance->available_balance, $amount, 2);
        $balance->save();

        return $balance;
    }

    public function creditAvailable(Shop $shop, string $amount): SellerBalance
    {
        $this->assertPositive($amount);
        $balance = $this->balanceFor($shop);
        $balance->available_balance = bcadd($balance->available_balance, $amount, 2);
        $balance->save();

        return $balance;
    }

    public function creditPending(Shop $shop, string $amount): SellerBalance
    {
        $this->assertPositive($amount);
        $balance = $this->balanceFor($shop);
        $balance->pending_balance = bcadd($balance->pending_balance, $amount, 2);
        $balance->total_earned = bcadd($balance->total_earned, $amount, 2);
        $balance->save();

        return $balance;
    }

    /**
     * Take money back from the seller, from pending first and then available. Balances may not go negative.
     */
    public function clawBack(Shop $shop, string $amount): SellerBalance
    {
        $this->assertPositive($amount);
        $balance = $this->balanceFor($shop);
        $fromPending = bccomp($balance->pending_balance, $amount, 2) >= 0 ? $amount : $balance->pending_balance;
        $remaining = bcsub($amount, $fromPending, 2);

        $balance->pending_balance = bcsub($balance->pending_balance, $fromPending, 2);
        $balance->available_balance = bcsub($balance->available_balance, $remaining, 2);
        $balance->total_earned = bcsub($balance->total_earned, $amount, 2);
        $balance->save();

        return $balance;
    }

    private function assertPositive(string $amount): void
    {
        if (! is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Amounts must be greater than zero.');
        }
    }
}
