<?php

namespace App\Enums;

enum PaymentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
