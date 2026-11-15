<?php

namespace App\Services\Finance;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\Shop;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Communication\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Seller payouts. Requesting holds the amount out of the available balance;
 * rejecting returns it; paying records the ledger entry.
 */
class WithdrawalService
{
    public function __construct(
        private LedgerService $ledger,
        private SettingsService $settings,
        private AuditLogger $auditLogger,
    ) {}

    public function request(Shop $shop, BankAccount $bankAccount, string $amount, User $actor): Withdrawal
    {
        $minimum = (string) $this->settings->get('finance', 'min_withdrawal', '0');

        if (bccomp($amount, $minimum, 2) < 0) {
            throw ValidationException::withMessages(['amount' => "The minimum withdrawal is {$minimum}."]);
        }

        if ($bankAccount->shop_id !== $shop->id) {
            throw ValidationException::withMessages(['bank_account_id' => 'Choose one of your own bank accounts.']);
        }

        return DB::transaction(function () use ($shop, $bankAccount, $amount, $actor) {
            try {
                $this->ledger->debitAvailable($shop, $amount);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            $withdrawal = $shop->withdrawals()->create([
                'bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'status' => WithdrawalStatus::Pending,
            ]);

            $this->auditLogger->log('withdrawal.requested', $withdrawal, newValues: ['amount' => $amount], actor: $actor);
            app(Notifier::class)->withdrawalRequested($withdrawal->setRelation('shop', $shop));

            return $withdrawal;
        });
    }

    public function approve(Withdrawal $withdrawal, User $actor): Withdrawal
    {
        return $this->move($withdrawal, [WithdrawalStatus::Pending], WithdrawalStatus::Approved, $actor);
    }

    public function markPaid(Withdrawal $withdrawal, User $actor, ?string $reference = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $actor, $reference) {
            $withdrawal = $this->move($withdrawal, [WithdrawalStatus::Approved], WithdrawalStatus::Paid, $actor, ['reference' => $reference]);

            $balance = $this->ledger->balanceFor($withdrawal->shop);
            $balance->total_withdrawn = bcadd($balance->total_withdrawn, $withdrawal->amount, 2);
            $balance->save();

            $this->ledger->record(TransactionType::Withdrawal, TransactionDirection::Debit, $withdrawal->amount, $withdrawal, [
                'shop' => $withdrawal->shop,
                'balance_after' => $balance->available_balance,
                'description' => 'Payout to '.$withdrawal->bankAccount->bank_name.' ••••'.$withdrawal->bankAccount->account_last4,
            ]);

            return $withdrawal;
        });
    }

    public function reject(Withdrawal $withdrawal, User $actor, string $reason): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $actor, $reason) {
            $withdrawal = $this->move($withdrawal, [WithdrawalStatus::Pending, WithdrawalStatus::Approved], WithdrawalStatus::Rejected, $actor, ['notes' => $reason]);
            $this->ledger->creditAvailable($withdrawal->shop, $withdrawal->amount);

            return $withdrawal;
        });
    }

    /**
     * @param  list<WithdrawalStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function move(Withdrawal $withdrawal, array $from, WithdrawalStatus $to, User $actor, array $extra = []): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $from, $to, $actor, $extra) {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => "A {$locked->status->label()} withdrawal cannot be marked {$to->label()}."]);
            }

            $old = $locked->status->value;
            $locked->forceFill(array_merge($extra, ['status' => $to, 'processed_by' => $actor->id, 'processed_at' => now()]))->save();
            $this->auditLogger->log('withdrawal.'.$to->value, $locked, ['status' => $old], ['status' => $to->value] + $extra, $actor);

            $locked->load('shop', 'bankAccount');
            app(Notifier::class)->withdrawalUpdated($locked);

            return $locked;
        });
    }
}
