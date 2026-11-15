<?php

namespace App\Enums;

enum SellerOrderStatus: string
{
    use HasLabel;

    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';
    case Refunded = 'refunded';
}
