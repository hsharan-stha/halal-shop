<?php

namespace Tests\Unit;

use App\Rules\JapanesePhone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JapanesePhoneTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function numbers(): array
    {
        return [
            'mobile hyphenated' => ['090-1234-5678', true],
            'mobile plain' => ['08012345678', true],
            'tokyo landline' => ['03-1234-5678', true],
            'full width digits' => ['０９０－１２３４－５６７８', true],
            'international' => ['+81 90 1234 5678', true],
            'too short' => ['12345', false],
            'no leading zero' => ['9012345678', false],
            'letters' => ['090-abcd-5678', false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_validates_japanese_numbers(string $number, bool $valid): void
    {
        $this->assertSame($valid, JapanesePhone::isValid($number));
    }
}
