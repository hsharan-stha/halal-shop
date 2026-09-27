<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ProductStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'success',
            self::Archived => 'warning',
        };
    }
}
