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
}
