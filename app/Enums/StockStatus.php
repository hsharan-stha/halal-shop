<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum StockStatus: string
{
    use HasLabel;

    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
        };
    }

    public static function for(int $sellable, int $threshold): self
    {
        return match (true) {
            $sellable <= 0 => self::OutOfStock,
            $sellable <= $threshold => self::LowStock,
            default => self::InStock,
        };
    }
}
