<?php

namespace App\Enums;

enum RefundStatus: string
{
    use HasLabel;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
}
