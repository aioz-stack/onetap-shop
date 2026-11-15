<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SellerOrderStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\SellerOrder;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Finance\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller money pages. Every route here sits behind `password.confirm`.
 */
class Finance extends SellerController
{
    public const BANKS = ['BDO', 'BPI', 'Metrobank', 'Landbank', 'PNB', 'Security Bank', 'UnionBank', 'RCBC', 'China Bank', 'GCash', 'Maya'];

    public function __construct(private WithdrawalService $withdrawals, private SettingsService $settings, private AuditLogger $auditLogger) {}

    public function overview(Request $request): Response
    {
        $shop = $this->shop($request)->load('balance');
        $completed = fn () => SellerOrder::query()->where('shop_id', $shop->id)->where('status', SellerOrderStatus::Completed);
        $monthStart = Carbon::today()->startOfMonth();

        return Inertia::render('Seller/Finance/Overview', [
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
            'bankAccounts' => $shop->bankAccounts()->orderByDesc('is_default')->get()->map(fn (BankAccount $account) => $this->bankRow($account)),
            'recentWithdrawals' => $shop->withdrawals()->with('bankAccount')->latest('id')->limit(5)->get()->map(fn ($withdrawal) => [
                'id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status->value,
                'bank' => $withdrawal->bankAccount ? "{$withdrawal->bankAccount->bank_name} ••••{$withdrawal->bankAccount->account_last4}" : null,
                'reference' => $withdrawal->reference,
                'notes' => $withdrawal->notes,
                'created_at' => $withdrawal->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Monthly income statement plus per-order income details for the chosen month.
     */
    public function statement(Request $request): Response
    {
        $shop = $this->shop($request);
        $month = Carbon::createFromFormat('Y-m', (string) $request->query('month', now()->format('Y-m'))) ?: now();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $completed = SellerOrder::query()->where('shop_id', $shop->id)->where('status', SellerOrderStatus::Completed)->whereBetween('completed_at', [$start, $end]);

        $statement = [
            'gross_sales' => Money::format((clone $completed)->sum('items_subtotal')),
            'vouchers' => Money::format((clone $completed)->sum('discount')),
            'commission' => Money::format((clone $completed)->sum('commission_amount')),
            'net_income' => Money::format((clone $completed)->sum('seller_earning')),
            'orders' => (clone $completed)->count(),
            'refunds' => Money::format($shop->sellerOrders()->where('status', SellerOrderStatus::Refunded)->whereBetween('updated_at', [$start, $end])->sum('seller_earning')),
        ];

        return Inertia::render('Seller/Finance/Statement', [
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
            'statement' => $statement,
            'details' => (clone $completed)->with('order:id,order_number')->latest('completed_at')->paginate(20)->withQueryString()->through(fn (SellerOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'items_subtotal' => $order->items_subtotal,
                'discount' => $order->discount,
                'commission_amount' => $order->commission_amount,
                'seller_earning' => $order->seller_earning,
                'released' => (bool) $order->earnings_released_at,
            ]),
            'months' => collect(range(0, 11))->map(fn ($offset) => now()->startOfMonth()->subMonthsNoOverflow($offset))->map(fn ($date) => ['value' => $date->format('Y-m'), 'label' => $date->format('F Y')]),
        ]);
    }

    public function transactions(Request $request): Response
    {
        return Inertia::render('Seller/Finance/Transactions', [
            'transactions' => $this->shop($request)->transactions()->latest('id')->paginate(20)->through(fn ($transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'direction' => $transaction->direction->value,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storeBankAccount(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'bank_name' => ['required', 'in:'.implode(',', self::BANKS)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^[0-9 -]{8,20}$/'],
        ], ['account_number.regex' => 'Enter 8 to 20 digits.']);

        if ($shop->bankAccounts()->count() >= 5) {
            return back()->withErrors(['account_number' => 'You can save up to 5 payout accounts.']);
        }

        $number = preg_replace('/\D/', '', $validated['account_number']);
        $account = $shop->bankAccounts()->create([
            'bank_name' => $validated['bank_name'],
            'account_name' => $validated['account_name'],
            'account_number' => $number,
            'account_last4' => substr($number, -4),
            'is_default' => ! $shop->bankAccounts()->exists(),
        ]);
        $this->auditLogger->log('bank_account.added', $account, newValues: ['bank' => $account->bank_name, 'last4' => $account->account_last4]);

        return back()->with('success', 'Payout account saved.');
    }

    public function defaultBankAccount(Request $request, BankAccount $account): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($account->shop_id === $shop->id, 404);
        $shop->bankAccounts()->update(['is_default' => false]);
        $account->update(['is_default' => true]);

        return back()->with('success', 'Default payout account updated.');
    }

    public function destroyBankAccount(Request $request, BankAccount $account): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($account->shop_id === $shop->id, 404);

        if ($account->withdrawals()->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Approved])->exists()) {
            return back()->with('error', 'This account has a payout in progress.');
        }

        if ($account->withdrawals()->exists()) {
            return back()->with('error', 'Accounts with past payouts are kept for your records.');
        }

        $this->auditLogger->log('bank_account.removed', $account, ['bank' => $account->bank_name, 'last4' => $account->account_last4]);
        $account->delete();

        if (! $shop->bankAccounts()->where('is_default', true)->exists()) {
            $shop->bankAccounts()->oldest('id')->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Payout account removed.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'bank_account_id' => ['required', 'integer'],
        ]);

        $account = $shop->bankAccounts()->find($validated['bank_account_id']);

        if (! $account) {
            return back()->withErrors(['bank_account_id' => 'Choose one of your payout accounts.']);
        }

        $this->withdrawals->request($shop, $account, Money::format($validated['amount']), $request->user());

        return back()->with('success', 'Withdrawal requested. Onetap will review and send it to your account.');
    }

    /**
     * @return array<string, mixed>
     */
    private function bankRow(BankAccount $account): array
    {
        return ['id' => $account->id, 'bank_name' => $account->bank_name, 'account_name' => $account->account_name, 'last4' => $account->account_last4, 'is_default' => $account->is_default];
    }
}
