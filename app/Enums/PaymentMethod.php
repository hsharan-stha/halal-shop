<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PaymentMethod: string
{
    use HasLabel;

    case BankTransfer = 'bank_transfer';
    case CashOnDelivery = 'cash_on_delivery';
}
