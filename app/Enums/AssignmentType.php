<?php

namespace App\Enums;

enum AssignmentType: string
{
    use HasLabel;

    case Pickup = 'pickup';
    case Delivery = 'delivery';
    case Return = 'return';
}
