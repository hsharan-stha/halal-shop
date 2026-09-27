<?php

namespace App\Rules;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The variant must belong to a product the current request may see, so a halal
 * shop cannot reference another shop's stock.
 */
class VariantInOwnShop implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = ProductVariant::query()->whereKey($value)->whereHas('product')->exists();

        if (! $exists) {
            $fail('validation.variant_not_in_shop')->translate();
        }
    }
}
