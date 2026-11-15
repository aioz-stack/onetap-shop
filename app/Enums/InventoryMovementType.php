<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    use HasLabel;

    case Restock = 'restock';
    case Sale = 'sale';
    case Reserve = 'reserve';
    case Release = 'release';
    case Adjustment = 'adjustment';
    case Return = 'return';
}
