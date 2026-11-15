<?php

namespace App\Services\Logistics;

use App\Enums\AssignmentStatus;
use App\Enums\CollectionStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\RiderCollection;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What a rider does with their own jobs. Every status change goes through ShipmentStatusService,
 * so seller orders, stock and customer notifications stay in step.
 */
class DeliveryService
{
    /**
     * Steps a rider may take from each status, in the order the app shows them.
     */
    public const RIDER_STEPS = [
        'assigned' => ['accepted'],
        'accepted' => ['arrived_at_pickup'],
        'arrived_at_pickup' => ['picked_up'],
        'picked_up' => ['in_transit'],
        'in_transit' => ['out_for_delivery'],
        'rescheduled' => ['out_for_delivery', 'returned_to_seller'],
        'failed_delivery' => ['rescheduled', 'returned_to_seller'],
        'customer_unavailable' => ['rescheduled', 'returned_to_seller'],
    ];

    /**
     * Status values a rider may send to the single "update status" endpoint (web and API).
     */
    public const UPDATE_STATUSES = ['declined', 'accepted', 'arrived_at_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'failed_delivery', 'customer_unavailable', 'rescheduled', 'returned_to_seller'];

    public function __construct(private ShipmentStatusService $statuses, private RiderShiftService $shifts) {}

    /**
     * One entry point for every non-delivery update: decline, the step-by-step flow and failed attempts.
     *
     * @param  array{lat?: float|null, lng?: float|null}  $location
     */
    public function update(Shipment $shipment, RiderProfile $rider, string $status, ?string $note = null, array $location = []): Shipment
    {
        if ($status === 'declined') {
            if (blank($note)) {
                throw ValidationException::withMessages(['note' => 'Say why you are declining this job.']);
            }

            return $this->decline($shipment, $rider, $note);
        }

        $next = ShipmentStatus::from($status);

        if (in_array($next, [ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable], true)) {
            if (blank($note)) {
                throw ValidationException::withMessages(['note' => 'Describe what happened at the address.']);
            }

            return $this->fail($shipment, $rider, $next, $note, $location);
        }

        return $this->advance($shipment, $rider, $next, $note, $location);
    }

    /**
     * Next steps the rider app offers for a shipment.
     *
     * @return list<string>
     */
    public static function nextSteps(Shipment $shipment): array
    {
        $steps = self::RIDER_STEPS[$shipment->status->value] ?? [];

        if ($shipment->status === ShipmentStatus::OutForDelivery) {
            $steps = ['delivered', 'failed_delivery', 'customer_unavailable'];
        }

        return $steps;
    }

    /**
     * The rider's open job for a shipment, or 404: riders only touch their own assignments.
     */
    public function jobFor(Shipment $shipment, RiderProfile $rider): DeliveryAssignment
    {
        $job = $shipment->assignments()
            ->where('rider_profile_id', $rider->id)
            ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Accepted])
            ->latest('id')
            ->first();

        abort_unless($job, 404);

        return $job;
    }

    /**
     * Move a shipment one step along the rider flow (everything except delivered and failures).
     *
     * @param  array{lat?: float|null, lng?: float|null}  $location
     */
    public function advance(Shipment $shipment, RiderProfile $rider, ShipmentStatus $next, ?string $note = null, array $location = []): Shipment
    {
        $this->jobFor($shipment, $rider);
        $this->shifts->assertOnShift($rider);

        if (! in_array($next->value, self::RIDER_STEPS[$shipment->status->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "You cannot mark a {$shipment->status->label()} parcel as {$next->label()} from the rider app."]);
        }

        if ($next === ShipmentStatus::ReturnedToSeller && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Say why the parcel is going back to the shop.']);
        }

        $this->shifts->touch($rider, $location['lat'] ?? null, $location['lng'] ?? null);

        return $this->statuses->transition($shipment, $next, $rider->user, $note, $location);
    }

    /**
     * Decline a job that has not been accepted yet; it goes back to the partner's queue.
     */
    public function decline(Shipment $shipment, RiderProfile $rider, string $reason): Shipment
    {
        $job = $this->jobFor($shipment, $rider);

        if ($shipment->status !== ShipmentStatus::Assigned) {
            throw ValidationException::withMessages(['status' => 'Only new jobs can be declined. Ask your dispatcher to reassign accepted jobs.']);
        }

        return DB::transaction(function () use ($shipment, $rider, $job, $reason) {
            $updated = $this->statuses->transition($shipment, ShipmentStatus::PendingPickup, $rider->user, "Declined by rider: {$reason}");
            $job->forceFill(['status' => AssignmentStatus::Declined])->save();

            return $updated;
        });
    }

    /**
     * Failed attempt at the customer's door.
     *
     * @param  array{lat?: float|null, lng?: float|null}  $location
     */
    public function fail(Shipment $shipment, RiderProfile $rider, ShipmentStatus $reason, string $note, array $location = []): Shipment
    {
        $this->jobFor($shipment, $rider);
        $this->shifts->assertOnShift($rider);

        if (! in_array($reason, [ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable], true) || $shipment->status !== ShipmentStatus::OutForDelivery) {
            throw ValidationException::withMessages(['status' => 'Only parcels that are out for delivery can be marked as failed.']);
        }

        return $this->statuses->transition($shipment, $reason, $rider->user, $note, $location);
    }

    /**
     * Hand-over: proof of delivery, COD collection and the Delivered status in one transaction.
     *
     * @param  array{recipient_name: string, recipient_relation?: string|null, amount_collected?: numeric-string|null, lat?: float|null, lng?: float|null, note?: string|null}  $data
     */
    public function complete(Shipment $shipment, RiderProfile $rider, UploadedFile $photo, array $data): Shipment
    {
        $this->jobFor($shipment, $rider);
        $this->shifts->assertOnShift($rider);

        if ($shipment->status !== ShipmentStatus::OutForDelivery) {
            throw ValidationException::withMessages(['status' => 'Mark the parcel out for delivery first.']);
        }

        $due = Money::format($shipment->cod_amount);
        $collected = Money::format($data['amount_collected'] ?? $due);

        if (bccomp($due, '0', 2) > 0 && bccomp($collected, '0', 2) <= 0) {
            throw ValidationException::withMessages(['amount_collected' => 'Enter the cash you collected.']);
        }

        $path = $photo->storeAs("delivery-proofs/{$shipment->id}", Str::uuid().'.'.$photo->extension(), 'local');
        $location = ['lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null];

        return DB::transaction(function () use ($shipment, $rider, $data, $path, $location, $due, $collected) {
            $shipment->proof()->create([
                'rider_profile_id' => $rider->id,
                'photo_path' => $path,
                'recipient_name' => $data['recipient_name'],
                'recipient_relation' => $data['recipient_relation'] ?? null,
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'delivered_at' => now(),
            ]);

            if (bccomp($due, '0', 2) > 0) {
                RiderCollection::query()->create([
                    'shipment_id' => $shipment->id,
                    'rider_profile_id' => $rider->id,
                    'amount_due' => $due,
                    'amount_collected' => $collected,
                    'collected_at' => now(),
                    'status' => bccomp($due, $collected, 2) === 0 ? CollectionStatus::Collected : CollectionStatus::Discrepancy,
                ]);
            }

            $note = 'Received by '.$data['recipient_name'].(filled($data['recipient_relation'] ?? null) ? " ({$data['recipient_relation']})" : '');
            $delivered = $this->statuses->transition($shipment, ShipmentStatus::Delivered, $rider->user, trim($note.' '.($data['note'] ?? '')), $location);
            $this->shifts->touch($rider, $location['lat'], $location['lng']);
            $this->markPaidIfSettled($delivered);

            return $delivered;
        });
    }

    /**
     * COD orders are paid once every one of their parcels has been delivered and its cash collected.
     */
    private function markPaidIfSettled(Shipment $shipment): void
    {
        $sellerOrder = $shipment->sellerOrder()->first();
        $collected = Money::format($shipment->collection()->value('amount_collected'));

        if (bccomp(Money::format($shipment->cod_amount), '0', 2) === 0 || bccomp($collected, Money::format($sellerOrder->total), 2) >= 0) {
            $sellerOrder->forceFill(['paid_at' => $sellerOrder->paid_at ?? now()])->save();
        }

        /** @var Order $order */
        $order = $sellerOrder->order()->with('sellerOrders.shipment')->first();
        $active = $order->sellerOrders->reject(fn ($item) => $item->status->value === 'cancelled');

        if ($active->every(fn ($item) => $item->paid_at !== null)) {
            $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();
            $order->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
        }
    }

    /**
     * What a rider earned on a given day: delivered parcels × the partner's per-delivery rate.
     *
     * @return array{delivered: int, rate: string, earnings: string}
     */
    public function earningsFor(RiderProfile $rider, ?Carbon $day = null): array
    {
        $day ??= today();
        $delivered = $rider->assignments()
            ->where('status', AssignmentStatus::Completed)
            ->whereHas('shipment', fn ($shipments) => $shipments->where('status', ShipmentStatus::Delivered)->whereDate('delivered_at', $day))
            ->count();
        $rate = Money::format($rider->logisticsPartner?->rider_fee_per_delivery);

        return ['delivered' => $delivered, 'rate' => $rate, 'earnings' => Money::format(bcmul($rate, (string) $delivered, 2))];
    }
}
