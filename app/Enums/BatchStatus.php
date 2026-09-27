<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum BatchStatus: string
{
    use HasLabel;

    case Available = 'available';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Disposed = 'disposed';

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Quarantined => 'warning',
            self::Expired => 'danger',
            self::Disposed => 'neutral',
        };
    }

    /**
     * Statuses whose units still physically exist in the warehouse.
     *
     * @return list<self>
     */
    public static function onHand(): array
    {
        return [self::Available, self::Quarantined, self::Expired];
    }
}
