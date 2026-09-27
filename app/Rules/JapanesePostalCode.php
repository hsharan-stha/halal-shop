<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Japanese postal code: 7 digits, optionally written as 123-4567 (full-width digits accepted).
 */
class JapanesePostalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{7}$/', self::digits($value))) {
            $fail('validation.japanese_postal_code')->translate();
        }
    }

    /**
     * Canonical 123-4567 form.
     */
    public static function normalize(string $value): string
    {
        $digits = self::digits($value);

        return strlen($digits) === 7 ? substr($digits, 0, 3).'-'.substr($digits, 3) : trim($value);
    }

    private static function digits(string $value): string
    {
        return preg_replace('/[\s\-‐－ー〒]/u', '', mb_convert_kana(trim($value), 'n')) ?? '';
    }
}
