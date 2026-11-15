<?php

namespace App\Services\Finance;

use App\Enums\CollectionStatus;
use App\Enums\RemittanceStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\RiderCollection;
use App\Models\RiderProfile;
use App\Models\RiderRemittance;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Communication\Notifier;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Riders hand the day's COD cash to their logistics partner; the partner confirms or disputes it.
 */
class RemittanceService
{
    public function __construct(private LedgerService $ledger, private AuditLogger $auditLogger) {}

    /**
     * The rider declares how much cash they handed over. Every unremitted collection up to that day is included.
     */
    public function submit(RiderProfile $rider, string $amount, ?Carbon $date = null, ?string $notes = null, ?User $actor = null): RiderRemittance
    {
        $date ??= today();

        return DB::transaction(function () use ($rider, $amount, $date, $notes, $actor) {
            $collections = RiderCollection::query()
                ->where('rider_profile_id', $rider->id)
                ->whereNull('rider_remittance_id')
                ->whereDate('collected_at', '<=', $date)
                ->lockForUpdate()
                ->get();

            if ($collections->isEmpty()) {
                throw ValidationException::withMessages(['amount' => 'There is no collected cash waiting to be remitted.']);
            }

            $expected = Money::format($collections->sum(fn ($collection) => (float) $collection->amount_collected));
            $remitted = Money::format($amount);

            $remittance = RiderRemittance::query()->create([
                'rider_profile_id' => $rider->id,
                'logistics_partner_id' => $rider->logistics_partner_id,
                'remittance_date' => $date->toDateString(),
                'expected_amount' => $expected,
                'remitted_amount' => $remitted,
                'discrepancy' => bcsub($remitted, $expected, 2),
                'status' => RemittanceStatus::Submitted,
                'notes' => $notes,
            ]);

            RiderCollection::query()->whereKey($collections->pluck('id'))->update(['rider_remittance_id' => $remittance->id]);
            $this->auditLogger->log('remittance.submitted', $remittance, newValues: ['expected' => $expected, 'remitted' => $remitted], actor: $actor ?? $rider->user);
            app(Notifier::class)->remittanceSubmitted($remittance);

            return $remittance;
        });
    }

    /**
     * The partner counted the cash. A mismatch keeps the remittance disputed and flags its collections.
     */
    public function confirm(RiderRemittance $remittance, User $actor, string $receivedAmount, ?string $notes = null): RiderRemittance
    {
        return DB::transaction(function () use ($remittance, $actor, $receivedAmount, $notes) {
            $locked = RiderRemittance::query()->lockForUpdate()->findOrFail($remittance->id);

            if ($locked->status === RemittanceStatus::Confirmed) {
                throw ValidationException::withMessages(['received_amount' => 'This remittance was already confirmed.']);
            }

            $received = Money::format($receivedAmount);
            $discrepancy = bcsub($received, $locked->expected_amount, 2);
            $matches = bccomp($discrepancy, '0', 2) === 0;

            $locked->forceFill([
                'remitted_amount' => $received,
                'discrepancy' => $discrepancy,
                'status' => $matches ? RemittanceStatus::Confirmed : RemittanceStatus::Disputed,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'notes' => trim(($locked->notes ? $locked->notes."\n" : '').($notes ?? '')) ?: null,
            ])->save();

            $locked->collections()->update(['status' => $matches ? CollectionStatus::Remitted : CollectionStatus::Discrepancy]);

            if ($matches) {
                $this->ledger->record(TransactionType::Remittance, TransactionDirection::Credit, $received, $locked, [
                    'logistics_partner_id' => $locked->logistics_partner_id,
                    'description' => "COD remitted by {$locked->rider->user->name} for {$locked->remittance_date->toDateString()}",
                ]);
            }

            $this->auditLogger->log($matches ? 'remittance.confirmed' : 'remittance.disputed', $locked, newValues: ['received' => $received, 'discrepancy' => $discrepancy], actor: $actor);
            app(Notifier::class)->remittanceReviewed($locked);

            return $locked;
        });
    }
}
