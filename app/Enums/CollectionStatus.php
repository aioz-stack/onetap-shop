<?php

namespace App\Enums;

enum CollectionStatus: string
{
    use HasLabel;

    case Collected = 'collected';
    case Remitted = 'remitted';
    case Discrepancy = 'discrepancy';
}
