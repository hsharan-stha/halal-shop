<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum PurchaseOrderStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Ordered => 'info',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isReceivable(): bool
    {
        return in_array($this, [self::Ordered, self::PartiallyReceived], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }
}
