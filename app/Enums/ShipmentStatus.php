<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    use HasLabel;

    case PendingPickup = 'pending_pickup';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case ArrivedAtPickup = 'arrived_at_pickup';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case FailedDelivery = 'failed_delivery';
    case CustomerUnavailable = 'customer_unavailable';
    case Rescheduled = 'rescheduled';
    case ReturnedToSeller = 'returned_to_seller';

    /**
     * The delivery flow. Every change goes through ShipmentStatusService, which enforces this map.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPickup => [self::Assigned],
            self::Assigned => [self::Accepted, self::PendingPickup],
            self::Accepted => [self::ArrivedAtPickup, self::PendingPickup],
            self::ArrivedAtPickup => [self::PickedUp, self::PendingPickup],
            self::PickedUp => [self::InTransit],
            self::InTransit => [self::OutForDelivery],
            self::OutForDelivery => [self::Delivered, self::FailedDelivery, self::CustomerUnavailable],
            self::FailedDelivery, self::CustomerUnavailable => [self::Rescheduled, self::ReturnedToSeller],
            self::Rescheduled => [self::OutForDelivery, self::ReturnedToSeller],
            self::Delivered, self::ReturnedToSeller => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Still at the shop: the parcel has not been collected yet.
     */
    public function isBeforePickup(): bool
    {
        return in_array($this, [self::PendingPickup, self::Assigned, self::Accepted, self::ArrivedAtPickup], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::ReturnedToSeller], true);
    }

    /**
     * Shipment-management tabs for the logistics panel.
     *
     * @return array<string, array{label: string, statuses: list<self>}>
     */
    public static function tabs(): array
    {
        return [
            'incoming' => ['label' => 'Pickup requests', 'statuses' => [self::PendingPickup]],
            'assigned' => ['label' => 'Assigned', 'statuses' => [self::Assigned, self::Accepted, self::ArrivedAtPickup]],
            'in_transit' => ['label' => 'In transit', 'statuses' => [self::PickedUp, self::InTransit]],
            'out_for_delivery' => ['label' => 'Out for delivery', 'statuses' => [self::OutForDelivery, self::Rescheduled]],
            'delivered' => ['label' => 'Delivered', 'statuses' => [self::Delivered]],
            'failed' => ['label' => 'Failed', 'statuses' => [self::FailedDelivery, self::CustomerUnavailable]],
            'returned' => ['label' => 'Returned', 'statuses' => [self::ReturnedToSeller]],
        ];
    }
}
