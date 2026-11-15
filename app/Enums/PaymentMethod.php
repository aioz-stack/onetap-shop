<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use HasLabel;

    case Cod = 'cod';
    case Gcash = 'gcash';
    case Maya = 'maya';
    case Card = 'card';
}
