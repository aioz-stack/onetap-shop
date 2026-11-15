<?php

namespace App\Services\Logistics;

use App\Enums\AssignmentStatus;
use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Enums\RoleName;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Identity\UserAccountService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logistics partners manage their own riders.
 */
class RiderService
{
    public function __construct(
        private UserAccountService $accounts,
        private ShipmentStatusService $statuses,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{name: string, username: string, email: string, phone?: string|null, password: string}  $account
     * @param  array{vehicle_type?: string|null, plate_number?: string|null, license_number?: string|null}  $vehicle
     */
    public function create(LogisticsPartner $partner, array $account, array $vehicle, bool $activate, User $actor): RiderProfile
    {
        return DB::transaction(function () use ($partner, $account, $vehicle, $activate, $actor) {
            $user = $this->accounts->create($account, [RoleName::Rider], $actor);

            $rider = $user->riderProfile()->create($vehicle + [
                'logistics_partner_id' => $partner->id,
                'status' => $activate ? RiderStatus::Active : RiderStatus::Pending,
                'availability' => RiderAvailability::Offline,
            ]);

            $this->auditLogger->log('rider.created', $rider, newValues: ['partner' => $partner->code, 'status' => $rider->status->value], actor: $actor);

            return $rider;
        });
    }

    /**
     * @param  array<string, mixed>  $account
     * @param  array<string, mixed>  $vehicle
     */
    public function update(RiderProfile $rider, array $account, array $vehicle, User $actor): RiderProfile
    {
        return DB::transaction(function () use ($rider, $account, $vehicle, $actor) {
            if (blank($account['password'] ?? null)) {
                unset($account['password']);
            }

            $rider->user->update($account);
            $rider->update($vehicle);
            $this->auditLogger->log('rider.updated', $rider, newValues: $vehicle, actor: $actor);

            return $rider;
        });
    }

    /**
     * Suspended riders go offline; their open jobs go back to the pickup queue.
     */
    public function setStatus(RiderProfile $rider, RiderStatus $status, User $actor): RiderProfile
    {
        if ($rider->status === $status) {
            throw ValidationException::withMessages(['status' => "This rider is already {$status->label()}."]);
        }

        return DB::transaction(function () use ($rider, $status, $actor) {
            $old = $rider->status->value;
            $rider->forceFill(['status' => $status] + ($status === RiderStatus::Suspended ? ['availability' => RiderAvailability::Offline] : []))->save();

            if ($status === RiderStatus::Suspended) {
                $rider->assignments()
                    ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])
                    ->with('shipment')
                    ->get()
                    ->each(function (DeliveryAssignment $assignment) use ($actor) {
                        if ($assignment->shipment->status->isBeforePickup() && $assignment->shipment->status !== ShipmentStatus::PendingPickup) {
                            $this->statuses->transition($assignment->shipment, ShipmentStatus::PendingPickup, $actor, 'Rider suspended');
                        }
                    });
            }

            $this->auditLogger->log('rider.'.$status->value, $rider, ['status' => $old], ['status' => $status->value], $actor);

            return $rider;
        });
    }

    /**
     * Delivery performance for a rider.
     *
     * @return array<string, mixed>
     */
    public function performance(RiderProfile $rider): array
    {
        $completed = $rider->assignments()->where('status', AssignmentStatus::Completed)->with('shipment:id,status,picked_up_at,delivered_at')->get();
        $delivered = $completed->filter(fn ($assignment) => $assignment->shipment?->status === ShipmentStatus::Delivered);
        $failedAttempts = (int) ShipmentStatusHistory::query()
            ->where('user_id', $rider->user_id)
            ->whereIn('to_status', [ShipmentStatus::FailedDelivery->value, ShipmentStatus::CustomerUnavailable->value])
            ->count();

        $minutes = $delivered
            ->filter(fn ($assignment) => $assignment->shipment->picked_up_at && $assignment->shipment->delivered_at)
            ->map(fn ($assignment) => $assignment->shipment->picked_up_at->diffInMinutes($assignment->shipment->delivered_at));

        return [
            'delivered' => $delivered->count(),
            'failed_attempts' => $failedAttempts,
            'success_rate' => ($delivered->count() + $failedAttempts) > 0 ? round($delivered->count() / ($delivered->count() + $failedAttempts) * 100, 1) : null,
            'avg_delivery_minutes' => $minutes->isNotEmpty() ? (int) round($minutes->avg()) : null,
            'open_jobs' => $rider->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->count(),
            'cod_collected' => Money::format($rider->collections()->sum('amount_collected')),
            'cod_unremitted' => Money::format($rider->collections()->whereNull('rider_remittance_id')->sum('amount_collected')),
        ];
    }
}
