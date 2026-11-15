<?php

namespace App\Enums;

enum ShopStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
