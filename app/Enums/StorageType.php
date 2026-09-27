<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum StorageType: string
{
    use HasLabel;

    case Ambient = 'ambient';
    case Chilled = 'chilled';
    case Frozen = 'frozen';

    public function color(): string
    {
        return match ($this) {
            self::Ambient => 'neutral',
            self::Chilled => 'info',
            self::Frozen => 'primary',
        };
    }
}
