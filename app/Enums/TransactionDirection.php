<?php

namespace App\Enums;

enum TransactionDirection: string
{
    use HasLabel;

    case Credit = 'credit';
    case Debit = 'debit';
}
