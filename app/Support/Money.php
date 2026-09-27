<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money helpers. Amounts are always integers in the currency's minor unit.
 * JPY has no minor unit, so 1 = ¥1.
 */
final class Money
{
    /**
     * @var array<string, array{symbol: string, decimals: int}>
     */
    private const CURRENCIES = [
        'JPY' => ['symbol' => '¥', 'decimals' => 0],
    ];

    public static function format(int $amount, ?string $currency = null): string
    {
        $currency ??= config('shop.currency');
        $meta = self::CURRENCIES[$currency] ?? throw new InvalidArgumentException("Unsupported currency [{$currency}].");

        $sign = $amount < 0 ? '-' : '';
        $value = abs($amount) / (10 ** $meta['decimals']);

        return $sign.$meta['symbol'].number_format($value, $meta['decimals']);
    }

    /**
     * Apply a percentage expressed in basis points (1000 = 10%) using integer math.
     */
    public static function percentage(int $amount, int $basisPoints, string $rounding = 'floor'): int
    {
        return self::divide($amount * $basisPoints, 10000, $rounding);
    }

    /**
     * Tax portion contained in a tax-inclusive amount: amount * rate / (1 + rate).
     */
    public static function includedTax(int $grossAmount, int $basisPoints, string $rounding = 'floor'): int
    {
        return self::divide($grossAmount * $basisPoints, 10000 + $basisPoints, $rounding);
    }

    public static function divide(int $numerator, int $denominator, string $rounding = 'floor'): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder === 0) {
            return $quotient;
        }

        return match ($rounding) {
            'ceil' => $numerator > 0 ? $quotient + 1 : $quotient,
            'round' => abs($remainder) * 2 >= abs($denominator) ? $quotient + ($numerator > 0 ? 1 : -1) : $quotient,
            default => $numerator < 0 ? $quotient - 1 : $quotient,
        };
    }
}
