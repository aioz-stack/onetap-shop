<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
