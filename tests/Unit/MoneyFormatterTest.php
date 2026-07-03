<?php

namespace Tests\Unit;

use App\Support\MoneyFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    #[DataProvider('minorAmountProvider')]
    public function test_it_formats_minor_units_as_fixed_decimal_strings(int $amountMinor, string $expected): void
    {
        $this->assertSame($expected, MoneyFormatter::formatMinorAmount($amountMinor));
    }

    public function test_it_rejects_negative_minor_units(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MoneyFormatter::formatMinorAmount(-1);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function minorAmountProvider(): array
    {
        return [
            'whole amount' => [125000, '1250.00'],
            'amount with decimals' => [125050, '1250.50'],
            'ninety minor units' => [90, '0.90'],
            'nine minor units' => [9, '0.09'],
            'zero' => [0, '0.00'],
        ];
    }
}
