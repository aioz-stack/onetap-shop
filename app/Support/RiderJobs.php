<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\ShipmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Logistics\DeliveryService;
use Illuminate\Database\Eloquent\Builder;

/**
 * How a rider's jobs are listed and shown, shared by the rider web app and the rider API.
 */
class RiderJobs
{
    public const OPEN = [AssignmentStatus::Assigned, AssignmentStatus::Accepted];

    /**
     * The rider's assignments, newest first, with what the list needs.
     *
     * @return Builder<DeliveryAssignment>
     */
    public static function query(RiderProfile $rider): Builder
    {
        return DeliveryAssignment::query()
            ->where('rider_profile_id', $rider->id)
            ->with(['shipment.sellerOrder:id,reference,shop_id', 'shipment.sellerOrder.shop:id,name']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(DeliveryAssignment $job): array
    {
        $shipment = $job->shipment;
        $dropoff = $shipment->dropoff_address ?? [];

        return [
            'id' => $shipment->id,
            'assignment_id' => $job->id,
            'tracking_number' => $shipment->tracking_number,
            'status' => $shipment->status->value,
            'job_status' => $job->status->value,
            'job_type' => $job->type->value,
            'open' => in_array($job->status, self::OPEN, true),
            'shop' => $shipment->sellerOrder?->shop?->name ?? ($shipment->pickup_address['name'] ?? null),
            'pickup_area' => self::area($shipment->pickup_address ?? []),
            'recipient' => $dropoff['recipient_name'] ?? null,
            'dropoff_area' => self::area($dropoff),
            'cod_amount' => $shipment->cod_amount,
            'attempts' => $shipment->attempts,
            'scheduled_at' => $shipment->scheduled_at?->toIso8601String(),
            'assigned_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  string  $photoUrl  where the rider's client can load the proof photo
     * @return array<string, mixed>
     */
    public static function detail(DeliveryAssignment $job, string $photoUrl): array
    {
        $shipment = $job->shipment->load(['items.orderItem:id,product_name,variation_label', 'statusHistories', 'proof', 'collection']);
        $open = in_array($job->status, self::OPEN, true);

        return self::row($job) + [
            'pickup_address' => $shipment->pickup_address,
            'dropoff_address' => $shipment->dropoff_address,
            'items' => $shipment->items->map(fn ($item) => ['name' => $item->orderItem?->product_name, 'variation' => $item->orderItem?->variation_label, 'quantity' => $item->quantity])->values(),
            'history' => $shipment->statusHistories->sortByDesc('id')->values()->map(fn ($history) => [
                'id' => $history->id,
                'to' => $history->to_status,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ]),
            'proof' => $shipment->proof ? [
                'recipient_name' => $shipment->proof->recipient_name,
                'recipient_relation' => $shipment->proof->recipient_relation,
                'delivered_at' => $shipment->proof->delivered_at?->toIso8601String(),
                'has_location' => $shipment->proof->lat !== null,
                'photo_url' => $photoUrl,
            ] : null,
            'collection' => $shipment->collection ? [
                'amount_due' => $shipment->collection->amount_due,
                'amount_collected' => $shipment->collection->amount_collected,
                'status' => $shipment->collection->status->value,
            ] : null,
            'next_steps' => $open ? DeliveryService::nextSteps($shipment) : [],
            'can_decline' => $open && $shipment->status === ShipmentStatus::Assigned,
        ];
    }

    /**
     * The rider's most recent job on a shipment (open or finished), or 404 for anyone else's parcel.
     */
    public static function find(RiderProfile $rider, Shipment $shipment): DeliveryAssignment
    {
        $job = self::query($rider)
            ->where('shipment_id', $shipment->id)
            ->orderByRaw('case when status in (?, ?) then 0 else 1 end', [AssignmentStatus::Assigned->value, AssignmentStatus::Accepted->value])
            ->latest('id')
            ->first();

        abort_unless($job, 404);

        return $job;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private static function area(array $address): string
    {
        return collect([$address['barangay'] ?? null, $address['city'] ?? null])->filter()->implode(', ');
    }
}
