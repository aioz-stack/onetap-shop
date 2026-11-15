<?php

namespace App\Services\Logistics;

use App\Enums\AssignmentStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Ordering\OrderStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place shipment statuses change. Enforces the delivery flow, writes
 * `shipment_status_histories`, and keeps the seller order in step:
 *
 *   picked up          → seller order Shipping (reservation becomes a sale)
 *   delivered          → seller order Delivered
 *   returned to seller → seller order Returned (items restocked)
 */
class ShipmentStatusService
{
    public function __construct(private OrderStatusService $orderStatus, private AuditLogger $auditLogger) {}

    /**
     * @param  array{lat?: float|string|null, lng?: float|string|null}  $location
     *
     * @throws ValidationException
     */
    public function transition(Shipment $shipment, ShipmentStatus $next, ?User $actor = null, ?string $note = null, array $location = []): Shipment
    {
        return DB::transaction(function () use ($shipment, $next, $actor, $note, $location) {
            /** @var Shipment $locked */
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $current = $locked->status;

            if (! $current->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => "A shipment that is {$current->label()} cannot be marked {$next->label()}.",
                ]);
            }

            $locked->status = $next;

            match ($next) {
                ShipmentStatus::PickedUp => $locked->picked_up_at ??= now(),
                ShipmentStatus::Delivered => $locked->delivered_at = now(),
                ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable => $locked->attempts++,
                default => null,
            };

            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $current->value,
                'to_status' => $next->value,
                'note' => $note,
                'lat' => $location['lat'] ?? null,
                'lng' => $location['lng'] ?? null,
                'user_id' => $actor?->id,
            ]);

            $this->syncSellerOrder($locked, $next, $actor, $note);
            $this->closeAssignments($locked, $next);
            $this->auditLogger->log('shipment.status_changed', $locked, ['status' => $current->value], ['status' => $next->value, 'note' => $note], $actor);

            return $locked;
        });
    }

    private function syncSellerOrder(Shipment $shipment, ShipmentStatus $next, ?User $actor, ?string $note): void
    {
        $order = $shipment->sellerOrder()->first();

        $target = match ($next) {
            ShipmentStatus::PickedUp => SellerOrderStatus::Shipping,
            ShipmentStatus::Delivered => SellerOrderStatus::Delivered,
            ShipmentStatus::ReturnedToSeller => SellerOrderStatus::Returned,
            default => null,
        };

        if ($order && $target && $order->status->canTransitionTo($target)) {
            $this->orderStatus->transition($order, $target, $actor, $note ?? "Shipment {$shipment->tracking_number}: {$next->label()}");
        }
    }

    /**
     * Delivery or return finishes the rider's job; going back to the pickup queue cancels it.
     */
    private function closeAssignments(Shipment $shipment, ShipmentStatus $next): void
    {
        $open = $shipment->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted]);

        if ($next->isFinal()) {
            $open->update(['status' => AssignmentStatus::Completed, 'completed_at' => now()]);
        } elseif ($next === ShipmentStatus::PendingPickup) {
            $open->update(['status' => AssignmentStatus::Cancelled]);
        } elseif ($next === ShipmentStatus::Accepted) {
            $open->update(['status' => AssignmentStatus::Accepted, 'accepted_at' => now()]);
        }
    }
}
