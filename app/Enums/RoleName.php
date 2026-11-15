<?php

namespace App\Enums;

enum RoleName: string
{
    use HasLabel;

    case Admin = 'admin';
    case Customer = 'customer';
    case Seller = 'seller';
    case Logistics = 'logistics';
    case Rider = 'rider';
}
