<?php

namespace App\Enums;

enum UserStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
}
