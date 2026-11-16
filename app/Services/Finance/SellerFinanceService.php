<?php

namespace App\Services\Finance;

use App\Enums\SellerOrderStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\Withdrawal;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A seller's money: balances, income statement and payout accounts. Shared by the Seller Center
 * finance pages and the seller app API (both behind a password confirmation).
 */
class SellerFinanceService
{
    public const BANKS = ['BDO', 'BPI', 'Metrobank', 'Landbank', 'PNB', 'Security Bank', 'UnionBank', 'RCBC', 'China Bank', 'GCash', 'Maya'];

    public const MAX_BANK_ACCOUNTS = 5;

    public function __construct(private SettingsService $settings, private AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Shop $shop): array
    {
        $shop->loadMissing('balance');
        $completed = fn () => SellerOrder::query()->where('shop_id', $shop->id)->where('status', SellerOrderStatus::Completed);
        $monthStart = Carbon::today()->startOfMonth();

        return [
            'balance' => [
                'available' => Money::format($shop->balance?->available_balance),
                'pending' => Money::format($shop->balance?->pending_balance),
                'total_earned' => Money::format($shop->balance?->total_earned),
                'total_withdrawn' => Money::format($shop->balance?->total_withdrawn),
            ],
            'income' => [
                'thisMonth' => Money::format($completed()->where('completed_at', '>=', $monthStart)->sum('seller_earning')),
                'lastMonth' => Money::format($completed()->whereBetween('completed_at', [$monthStart->copy()->subMonthNoOverflow(), $monthStart])->sum('seller_earning')),
                'toRelease' => Money::format($completed()->whereNull('earnings_released_at')->sum('seller_earning')),
                'inProgress' => Money::format(SellerOrder::query()->where('shop_id', $shop->id)->whereIn('status', [SellerOrderStatus::Pending, SellerOrderStatus::Processing, SellerOrderStatus::ReadyForPickup, SellerOrderStatus::Shipping, SellerOrderStatus::Delivered])->sum('seller_earning')),
            ],
            'holdDays' => (int) $this->settings->get('finance', 'earnings_hold_days', 3),
            'minWithdrawal' => Money::format($this->settings->get('finance', 'min_withdrawal', '500')),
            'bankAccounts' => $shop->bankAccounts()->orderByDesc('is_default')->get()->map(fn (BankAccount $account) => self::bankRow($account))->values(),
            'recentWithdrawals' => $shop->withdrawals()->with('bankAccount')->latest('id')->limit(5)->get()->map(fn (Withdrawal $withdrawal) => self::withdrawalRow($withdrawal))->values(),
        ];
    }

    /**
     * Monthly statement totals plus the query for the per-order details of that month.
     *
     * @return array{start: Carbon, statement: array<string, mixed>, details: Builder<SellerOrder>}
     */
    public function statement(Shop $shop, ?string $month): array
    {
        $date = ($month && preg_match('/^\d{4}-\d{2}$/', $month)) ? Carbon::createFromFormat('Y-m', $month) : now();
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        $completed = SellerOrder::query()->where('shop_id', $shop->id)->where('status', SellerOrderStatus::Completed)->whereBetween('completed_at', [$start, $end]);

        return [
            'start' => $start,
            'statement' => [
                'gross_sales' => Money::format((clone $completed)->sum('items_subtotal')),
                'vouchers' => Money::format((clone $completed)->sum('discount')),
                'commission' => Money::format((clone $completed)->sum('commission_amount')),
                'net_income' => Money::format((clone $completed)->sum('seller_earning')),
                'orders' => (clone $completed)->count(),
                'refunds' => Money::format($shop->sellerOrders()->where('status', SellerOrderStatus::Refunded)->whereBetween('updated_at', [$start, $end])->sum('seller_earning')),
            ],
            'details' => (clone $completed)->with('order:id,order_number')->latest('completed_at'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function statementRow(SellerOrder $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'completed_at' => $order->completed_at?->toIso8601String(),
            'items_subtotal' => $order->items_subtotal,
            'discount' => $order->discount,
            'commission_amount' => $order->commission_amount,
            'seller_earning' => $order->seller_earning,
            'released' => (bool) $order->earnings_released_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transactionRow($transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'direction' => $transaction->direction->value,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bankAccountRules(): array
    {
        return [
            'bank_name' => ['required', 'in:'.implode(',', self::BANKS)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^[0-9 -]{8,20}$/'],
        ];
    }

    /**
     * @param  array{bank_name: string, account_name: string, account_number: string}  $data
     *
     * @throws ValidationException
     */
    public function addBankAccount(Shop $shop, array $data): BankAccount
    {
        if ($shop->bankAccounts()->count() >= self::MAX_BANK_ACCOUNTS) {
            throw ValidationException::withMessages(['account_number' => 'You can save up to '.self::MAX_BANK_ACCOUNTS.' payout accounts.']);
        }

        $number = preg_replace('/\D/', '', $data['account_number']);
        $account = $shop->bankAccounts()->create([
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'],
            'account_number' => $number,
            'account_last4' => substr($number, -4),
            'is_default' => ! $shop->bankAccounts()->exists(),
        ]);
        $this->auditLogger->log('bank_account.added', $account, newValues: ['bank' => $account->bank_name, 'last4' => $account->account_last4]);

        return $account;
    }

    public function makeDefault(Shop $shop, BankAccount $account): BankAccount
    {
        abort_unless($account->shop_id === $shop->id, 404);

        DB::transaction(function () use ($shop, $account) {
            $shop->bankAccounts()->update(['is_default' => false]);
            $account->update(['is_default' => true]);
        });

        return $account;
    }

    /**
     * @throws ValidationException
     */
    public function removeBankAccount(Shop $shop, BankAccount $account): void
    {
        abort_unless($account->shop_id === $shop->id, 404);

        if ($account->withdrawals()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved])->exists()) {
            throw ValidationException::withMessages(['account' => 'This account has a payout in progress.']);
        }

        if ($account->withdrawals()->exists()) {
            throw ValidationException::withMessages(['account' => 'Accounts with past payouts are kept for your records.']);
        }

        $this->auditLogger->log('bank_account.removed', $account, ['bank' => $account->bank_name, 'last4' => $account->account_last4]);
        $account->delete();

        if (! $shop->bankAccounts()->where('is_default', true)->exists()) {
            $shop->bankAccounts()->oldest('id')->first()?->update(['is_default' => true]);
        }
    }

    /**
     * Payout accounts never expose the full number: bank, holder name and last 4 digits only.
     *
     * @return array<string, mixed>
     */
    public static function bankRow(BankAccount $account): array
    {
        return ['id' => $account->id, 'bank_name' => $account->bank_name, 'account_name' => $account->account_name, 'last4' => $account->account_last4, 'is_default' => $account->is_default];
    }

    /**
     * @return array<string, mixed>
     */
    public static function withdrawalRow(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => $withdrawal->amount,
            'status' => $withdrawal->status->value,
            'bank' => $withdrawal->bankAccount ? "{$withdrawal->bankAccount->bank_name} ••••{$withdrawal->bankAccount->account_last4}" : null,
            'reference' => $withdrawal->reference,
            'notes' => $withdrawal->notes,
            'created_at' => $withdrawal->created_at?->toIso8601String(),
            'processed_at' => $withdrawal->processed_at?->toIso8601String(),
        ];
    }
}
