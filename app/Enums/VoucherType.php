<?php

namespace App\Enums;

enum VoucherType: string
{
    use HasLabel;

    case Fixed = 'fixed';
    case Percent = 'percent';
    case FreeShipping = 'free_shipping';
}
