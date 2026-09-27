<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum UserStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Deactivated => 'neutral',
        };
    }
}
