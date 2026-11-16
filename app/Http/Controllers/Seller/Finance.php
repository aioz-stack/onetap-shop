<?php

namespace App\Http\Controllers\Seller;

use App\Models\BankAccount;
use App\Models\SellerOrder;
use App\Services\Finance\SellerFinanceService;
use App\Services\Finance\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller money pages. Every route here sits behind `password.confirm`. The figures and payout-account
 * rules live in SellerFinanceService, shared with the seller app API.
 */
class Finance extends SellerController
{
    public const BANKS = SellerFinanceService::BANKS;

    public function __construct(private WithdrawalService $withdrawals, private SellerFinanceService $finance) {}

    public function overview(Request $request): Response
    {
        return Inertia::render('Seller/Finance/Overview', $this->finance->overview($this->shop($request)));
    }

    /**
     * Monthly income statement plus per-order income details for the chosen month.
     */
    public function statement(Request $request): Response
    {
        $result = $this->finance->statement($this->shop($request), $request->query('month'));
        $start = $result['start'];

        return Inertia::render('Seller/Finance/Statement', [
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
            'statement' => $result['statement'],
            'details' => $result['details']->paginate(20)->withQueryString()->through(fn (SellerOrder $order) => SellerFinanceService::statementRow($order)),
            'months' => collect(range(0, 11))->map(fn ($offset) => now()->startOfMonth()->subMonthsNoOverflow($offset))->map(fn ($date) => ['value' => $date->format('Y-m'), 'label' => $date->format('F Y')]),
        ]);
    }

    public function transactions(Request $request): Response
    {
        return Inertia::render('Seller/Finance/Transactions', [
            'transactions' => $this->shop($request)->transactions()->latest('id')->paginate(20)->through(fn ($transaction) => SellerFinanceService::transactionRow($transaction)),
        ]);
    }

    public function storeBankAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate(SellerFinanceService::bankAccountRules(), ['account_number.regex' => 'Enter 8 to 20 digits.']);
        $this->finance->addBankAccount($this->shop($request), $validated);

        return back()->with('success', 'Payout account saved.');
    }

    public function defaultBankAccount(Request $request, BankAccount $account): RedirectResponse
    {
        $this->finance->makeDefault($this->shop($request), $account);

        return back()->with('success', 'Default payout account updated.');
    }

    public function destroyBankAccount(Request $request, BankAccount $account): RedirectResponse
    {
        try {
            $this->finance->removeBankAccount($this->shop($request), $account);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
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
}
