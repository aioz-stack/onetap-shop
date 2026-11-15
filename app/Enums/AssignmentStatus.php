<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    use HasLabel;

    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
