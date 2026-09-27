<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DeliveryDestination: string
{
    use HasLabel;

    case Customer = 'customer';
    case Shop = 'shop';
}
