<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum InventoryMovementType: string
{
    use HasLabel;

    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Damage = 'damage';
    case Adjustment = 'adjustment';
    case Expiry = 'expiry';
    case Transfer = 'transfer';
    case CancelledOrder = 'cancelled_order';

    public function color(): string
    {
        return match ($this) {
            self::Purchase, self::Return, self::CancelledOrder => 'success',
            self::Sale => 'primary',
            self::Damage, self::Expiry => 'danger',
            self::Adjustment => 'warning',
            self::Transfer => 'info',
        };
    }

    /**
     * Types staff may choose when writing stock off manually.
     *
     * @return list<self>
     */
    public static function disposals(): array
    {
        return [self::Damage, self::Expiry];
    }
}
