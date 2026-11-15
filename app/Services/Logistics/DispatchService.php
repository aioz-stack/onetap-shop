<?php

namespace App\Services\Logistics;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\PartnerStatus;
use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\DeliveryAssigned;
use App\Services\Administration\AuditLogger;
use App\Services\Communication\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who carries a shipment: partner routing (admin) and rider assignment (logistics).
 */
class DispatchService
{
    public function __construct(private ShipmentStatusService $statuses, private AuditLogger $auditLogger) {}

    /**
     * Give a waiting (or rescheduled) shipment to one of the partner's active riders.
     * Reassigning cancels the previous rider's open job.
     */
    public function assign(Shipment $shipment, RiderProfile $rider, User $actor, ?string $note = null): DeliveryAssignment
    {
        if ($rider->logistics_partner_id !== $shipment->logistics_partner_id) {
            throw ValidationException::withMessages(['rider_id' => 'Choose one of your own riders.']);
        }

        if ($rider->status !== RiderStatus::Active) {
            throw ValidationException::withMessages(['rider_id' => 'This rider is not active.']);
        }

        $assignment = DB::transaction(function () use ($shipment, $rider, $actor, $note) {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $status = $shipment->status;

            if (! in_array($status, [ShipmentStatus::PendingPickup, ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::Rescheduled], true)) {
                throw ValidationException::withMessages(['rider_id' => "A {$status->label()} shipment cannot be (re)assigned."]);
            }

            // Reassignment: the old rider's job is cancelled and the parcel goes back to the queue first.
            if (in_array($status, [ShipmentStatus::Assigned, ShipmentStatus::Accepted], true)) {
                $previous = $shipment->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->latest('id')->first()?->rider?->user;
                $this->statuses->transition($shipment, ShipmentStatus::PendingPickup, $actor, 'Reassigned');

                if ($previous && ! $previous->is($rider->user)) {
                    app(Notifier::class)->riderUnassigned($shipment, $previous);
                }
            } else {
                $shipment->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->update(['status' => AssignmentStatus::Cancelled]);
            }

            $assignment = $shipment->assignments()->create([
                'rider_profile_id' => $rider->id,
                'type' => $status === ShipmentStatus::Rescheduled ? AssignmentType::Delivery : AssignmentType::Pickup,
                'status' => AssignmentStatus::Assigned,
                'assigned_by' => $actor->id,
            ]);

            // A rescheduled parcel is already with the partner: the rider takes it straight out again.
            if ($status !== ShipmentStatus::Rescheduled) {
                $this->statuses->transition($shipment->refresh(), ShipmentStatus::Assigned, $actor, $note ?? "Assigned to {$rider->user->name}");
            } else {
                $shipment->statusHistories()->create(['from_status' => $status->value, 'to_status' => $status->value, 'note' => "Redelivery assigned to {$rider->user->name}", 'user_id' => $actor->id]);
            }

            return $assignment;
        });

        DB::afterCommit(fn () => $rider->user->notify(new DeliveryAssigned($assignment->load('shipment'))));

        return $assignment;
    }

    /**
     * Take the shipment back from its rider (before pickup) so it can be given to someone else.
     */
    public function unassign(Shipment $shipment, User $actor, string $reason): Shipment
    {
        if (! in_array($shipment->status, [ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup], true)) {
            throw ValidationException::withMessages(['status' => 'Only shipments waiting for pickup can be unassigned.']);
        }

        $rider = $shipment->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->latest('id')->first()?->rider?->user;
        $updated = $this->statuses->transition($shipment, ShipmentStatus::PendingPickup, $actor, "Unassigned: {$reason}");
        app(Notifier::class)->riderUnassigned($updated, $rider);

        return $updated;
    }

    /**
     * Admin: route a shipment that no partner covered to a partner.
     */
    public function routeToPartner(Shipment $shipment, LogisticsPartner $partner, User $admin): Shipment
    {
        if ($partner->status !== PartnerStatus::Active) {
            throw ValidationException::withMessages(['partner_id' => 'Choose an active logistics partner.']);
        }

        if (! $shipment->status->isBeforePickup() || $shipment->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])->exists()) {
            throw ValidationException::withMessages(['partner_id' => 'Only shipments that have not been assigned to a rider can be rerouted.']);
        }

        return DB::transaction(function () use ($shipment, $partner, $admin) {
            $from = $shipment->logistics_partner_id;
            $shipment->forceFill(['logistics_partner_id' => $partner->id])->save();
            $shipment->statusHistories()->create(['from_status' => $shipment->status->value, 'to_status' => $shipment->status->value, 'note' => "Routed to {$partner->name}", 'user_id' => $admin->id]);
            $this->auditLogger->log('shipment.routed', $shipment, ['logistics_partner_id' => $from], ['logistics_partner_id' => $partner->id], $admin);
            app(Notifier::class)->shipmentRouted($shipment, $partner);

            return $shipment;
        });
    }

    /**
     * Riders a partner can dispatch right now, least busy first.
     *
     * @return Collection<int, RiderProfile>
     */
    public function availableRiders(LogisticsPartner $partner)
    {
        return $partner->riders()
            ->where('status', RiderStatus::Active)
            ->with('user:id,name,phone')
            ->withCount(['assignments as open_jobs' => fn ($query) => $query->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])])
            ->get()
            ->sortBy([
                fn ($rider) => $rider->availability === RiderAvailability::Offline ? 1 : 0,
                fn ($rider) => $rider->open_jobs,
            ])
            ->values();
    }
}
