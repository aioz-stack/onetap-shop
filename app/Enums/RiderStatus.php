<?php

namespace App\Enums;

enum RiderStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
