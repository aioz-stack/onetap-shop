<?php

namespace App\Support;

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\SellerOrder;

/**
 * The customer's order history: order events (placed, preparing, packed, shipping, delivered…)
 * merged with the delivery steps of its shipment, so the history always matches the live
 * tracking status shown above it.
 *
 * Shipment steps that repeat an order event (picked up = Shipping, delivered, returned) or are
 * internal to logistics (waiting in the queue, rider accepted, reassignments) are left out.
 */
class OrderTimeline
{
    /**
     * Shipment steps the customer sees, with customer wording. `note` true shows the rider's note.
     *
     * @var array<string, array{label: string, note?: string|bool}>
     */
    private const SHIPMENT_STEPS = [
        'assigned' => ['label' => 'Rider assigned', 'note' => 'A rider will pick up your parcel from the shop.'],
        'arrived_at_pickup' => ['label' => 'Rider at the shop'],
        'in_transit' => ['label' => 'In transit'],
        'out_for_delivery' => ['label' => 'Out for delivery', 'note' => 'Your rider is on the way.'],
        'failed_delivery' => ['label' => 'Delivery attempt failed', 'note' => true],
        'customer_unavailable' => ['label' => 'Delivery attempt failed: recipient unavailable', 'note' => true],
        'rescheduled' => ['label' => 'Redelivery scheduled'],
    ];

    /**
     * @return list<array{status: string, label: string, note: string|null, at: string|null, source: string}>
     */
    public static function forCustomer(SellerOrder $order): array
    {
        $entries = [];

        foreach ($order->statusHistories as $history) {
            $entries[] = [
                'status' => $history->to_status,
                'label' => SellerOrderStatus::tryFrom($history->to_status)?->label() ?? $history->to_status,
                'note' => $history->note,
                'at' => $history->created_at,
                'source' => 'order',
                'sequence' => $history->id,
            ];
        }

        $shipment = $order->relationLoaded('shipment') ? $order->shipment : null;

        if ($shipment && $shipment->relationLoaded('statusHistories')) {
            foreach ($shipment->statusHistories as $history) {
                $step = self::SHIPMENT_STEPS[$history->to_status] ?? null;

                // Same-status rows are dispatcher bookkeeping (e.g. "Redelivery assigned to …").
                if (! $step || $history->from_status === $history->to_status) {
                    continue;
                }

                $note = $step['note'] ?? null;

                $entries[] = [
                    'status' => 'shipment.'.$history->to_status,
                    'label' => $step['label'],
                    'note' => $note === true ? $history->note : $note,
                    'at' => $history->created_at,
                    'source' => 'shipment',
                    'sequence' => $history->id,
                ];
            }
        }

        $entries = self::merge(
            array_values(array_filter($entries, fn ($entry) => $entry['source'] === 'order')),
            array_values(array_filter($entries, fn ($entry) => $entry['source'] === 'shipment')),
        );

        return array_map(fn ($entry) => [
            'status' => $entry['status'],
            'label' => $entry['label'],
            'note' => $entry['note'],
            'at' => $entry['at']?->toIso8601String(),
            'source' => $entry['source'],
        ], $entries);
    }

    /**
     * Where each event sits in the normal delivery flow; breaks ties between events logged in the same second.
     */
    private const FLOW_RANK = [
        'pending' => 0, 'unpaid' => 0, 'processing' => 1, 'ready_for_pickup' => 2,
        'shipment.assigned' => 3, 'shipment.arrived_at_pickup' => 4, 'shipping' => 5, 'shipment.in_transit' => 6,
        'shipment.out_for_delivery' => 7, 'shipment.failed_delivery' => 8, 'shipment.customer_unavailable' => 8,
        'shipment.rescheduled' => 9, 'delivered' => 10, 'completed' => 11,
    ];

    /**
     * Merge two lists that are each already in their own order (by id), oldest first. Each list keeps
     * its order; between lists the earlier timestamp wins, and on the same second the flow rank decides.
     *
     * @param  list<array<string, mixed>>  $orderEvents
     * @param  list<array<string, mixed>>  $shipmentEvents
     * @return list<array<string, mixed>>
     */
    private static function merge(array $orderEvents, array $shipmentEvents): array
    {
        $byId = fn ($a, $b) => $a['sequence'] <=> $b['sequence'];
        usort($orderEvents, $byId);
        usort($shipmentEvents, $byId);

        $merged = [];
        $i = $j = 0;

        while ($i < count($orderEvents) || $j < count($shipmentEvents)) {
            $order = $orderEvents[$i] ?? null;
            $shipment = $shipmentEvents[$j] ?? null;

            if (! $shipment || ($order && self::comesFirst($order, $shipment))) {
                $merged[] = $order;
                $i++;
            } else {
                $merged[] = $shipment;
                $j++;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $shipment
     */
    private static function comesFirst(array $order, array $shipment): bool
    {
        $orderAt = $order['at']?->getTimestamp() ?? 0;
        $shipmentAt = $shipment['at']?->getTimestamp() ?? 0;

        if ($orderAt !== $shipmentAt) {
            return $orderAt < $shipmentAt;
        }

        // Unknown statuses (cancelled, returned, refunds…) sort after delivery steps logged in the same second.
        return (self::FLOW_RANK[$order['status']] ?? 99) <= (self::FLOW_RANK[$shipment['status']] ?? 99);
    }

    /**
     * Whether a shipment status is one the customer timeline shows (used by tests and clients).
     */
    public static function showsShipmentStatus(ShipmentStatus $status): bool
    {
        return array_key_exists($status->value, self::SHIPMENT_STEPS);
    }
}
