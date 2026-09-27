<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    protected bool $seed = false;

    public function test_formats_yen_without_decimals(): void
    {
        $this->assertSame('¥1,980', Money::format(1980, 'JPY'));
        $this->assertSame('¥0', Money::format(0, 'JPY'));
        $this->assertSame('-¥500', Money::format(-500, 'JPY'));
    }

    /**
     * @return array<string, array{int, int, string, int}>
     */
    public static function includedTaxCases(): array
    {
        return [
            '10% of 1100 floor' => [1100, 1000, 'floor', 100],
            '8% of 1080 floor' => [1080, 800, 'floor', 80],
            '10% of 1980 floor' => [1980, 1000, 'floor', 180],
            '8% of 1000 floor' => [1000, 800, 'floor', 74],
            '8% of 1000 ceil' => [1000, 800, 'ceil', 75],
            '8% of 1000 round' => [1000, 800, 'round', 74],
        ];
    }

    #[DataProvider('includedTaxCases')]
    public function test_included_tax_uses_integer_arithmetic(int $gross, int $bp, string $rounding, int $expected): void
    {
        $this->assertSame($expected, Money::includedTax($gross, $bp, $rounding));
    }

    public function test_percentage_rounding(): void
    {
        $this->assertSame(99, Money::percentage(999, 1000, 'floor'));
        $this->assertSame(100, Money::percentage(999, 1000, 'ceil'));
        $this->assertSame(100, Money::percentage(999, 1000, 'round'));
        $this->assertSame(-100, Money::percentage(-999, 1000, 'floor'));
    }
}
