<?php

namespace App\Enums;

enum TransactionType: string
{
    use HasLabel;

    case SaleEarning = 'sale_earning';
    case Commission = 'commission';
    case DeliveryFee = 'delivery_fee';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case CodCollection = 'cod_collection';
    case Remittance = 'remittance';
    case PlatformVoucher = 'platform_voucher';
}
