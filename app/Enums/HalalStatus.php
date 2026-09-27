<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Halal classification chosen explicitly by an administrator. New products
 * always start as Unverified; nothing in the system derives this value.
 */
enum HalalStatus: string
{
    use HasLabel;

    case Unverified = 'unverified';
    case Certified = 'certified';
    case ManufacturerDeclared = 'manufacturer_declared';
    case NoAnimalIngredients = 'no_animal_ingredients';
    case NotHalal = 'not_halal';

    public function color(): string
    {
        return match ($this) {
            self::Certified => 'success',
            self::ManufacturerDeclared, self::NoAnimalIngredients => 'info',
            self::NotHalal => 'danger',
            self::Unverified => 'neutral',
        };
    }

    public function requiresCertificate(): bool
    {
        return $this === self::Certified;
    }
}
