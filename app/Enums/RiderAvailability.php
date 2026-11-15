<?php

namespace App\Enums;

enum RiderAvailability: string
{
    use HasLabel;

    case Offline = 'offline';
    case Available = 'available';
    case Busy = 'busy';
}
