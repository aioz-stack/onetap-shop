<?php

namespace App\Enums;

enum PartnerStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
