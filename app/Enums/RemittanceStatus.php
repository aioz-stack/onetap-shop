<?php

namespace App\Enums;

enum RemittanceStatus: string
{
    use HasLabel;

    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';
}
