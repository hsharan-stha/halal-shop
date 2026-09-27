<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Japanese domestic phone numbers (landline 10 digits, mobile/IP 11 digits,
 * leading 0) with optional hyphens, or +81 international format.
 */
class JapanesePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('validation.japanese_phone')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        $normalized = self::normalize($value);

        return (bool) preg_match('/^0\d{9,10}$/', $normalized);
    }

    public static function normalize(string $value): string
    {
        $value = mb_convert_kana(trim($value), 'n');
        $digits = preg_replace('/[\s\-‐－ー()（）]/u', '', $value) ?? '';

        if (str_starts_with($digits, '+81')) {
            $digits = '0'.ltrim(substr($digits, 3), '0');
        }

        return $digits;
    }
}
