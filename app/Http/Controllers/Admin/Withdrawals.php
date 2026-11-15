<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Finance\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller settlements: review, approve, record payment, or reject payout requests.
 */
class Withdrawals extends Controller
{
    public function __construct(private WithdrawalService $withdrawals) {}

    public function index(Request $request): Response
    {
        $status = WithdrawalStatus::tryFrom((string) $request->query('status', 'pending')) ?? WithdrawalStatus::Pending;
        $counts = [];

        foreach (WithdrawalStatus::cases() as $case) {
            $counts[$case->value] = Withdrawal::query()->where('status', $case)->count();
        }

        return Inertia::render('Admin/Finance/Withdrawals', [
            'withdrawals' => Withdrawal::query()
                ->with(['shop:id,name', 'shop.balance', 'bankAccount', 'processor:id,name'])
                ->where('status', $status)
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Withdrawal $withdrawal) => [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                    'fee' => $withdrawal->fee,
                    'status' => $withdrawal->status->value,
                    'reference' => $withdrawal->reference,
                    'notes' => $withdrawal->notes,
                    'shop' => $withdrawal->shop?->only(['id', 'name']),
                    'available_balance' => $withdrawal->shop?->balance?->available_balance,
                    'bank' => $withdrawal->bankAccount ? [
                        'bank_name' => $withdrawal->bankAccount->bank_name,
                        'account_name' => $withdrawal->bankAccount->account_name,
                        'last4' => $withdrawal->bankAccount->account_last4,
                    ] : null,
                    'processor' => $withdrawal->processor?->name,
                    'processed_at' => $withdrawal->processed_at?->toIso8601String(),
                    'created_at' => $withdrawal->created_at?->toIso8601String(),
                ]),
            'status' => $status->value,
            'counts' => $counts,
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $this->withdrawals->approve($withdrawal, $request->user());

        return back()->with('success', 'Withdrawal approved. Record the payment once the transfer is made.');
    }

    public function pay(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        $this->withdrawals->markPaid($withdrawal, $request->user(), $validated['reference']);

        return back()->with('success', 'Payment recorded.');
    }

    public function reject(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->withdrawals->reject($withdrawal, $request->user(), $validated['reason']);

        return back()->with('success', 'Withdrawal rejected. The amount was returned to the seller’s available balance.');
    }
}
