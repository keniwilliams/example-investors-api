<?php

namespace Tests\Unit;

use App\DataTransferObjects\Imports\InvestorCsvRowDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvestorCsvRowDTOTest extends TestCase
{
    public function test_it_normalises_valid_csv_data(): void
    {
        $dto = InvestorCsvRowDTO::fromCsvData([
            'investor_id' => '  INV-001  ',
            'name' => '  Ada Lovelace  ',
            'age' => '037',
            'investment_amount' => '001250.5',
            'investment_date' => '2026-07-03',
        ]);

        $this->assertInstanceOf(InvestorCsvRowDTO::class, $dto);
        $this->assertSame('INV-001', $dto->externalId);
        $this->assertSame('Ada Lovelace', $dto->name);
        $this->assertSame(37, $dto->age);
        $this->assertSame(125050, $dto->amountMinor);
        $this->assertSame('2026-07-03', $dto->investmentDate);
        $this->assertSame('INV-001|2026-07-03', $dto->investmentNaturalKey());
        $this->assertSame([
            'external_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 37,
        ], $dto->investorPayload());
        $this->assertSame([
            'amount_minor' => 125050,
            'investment_date' => '2026-07-03',
        ], $dto->investmentPayload());
    }

    #[DataProvider('validAmountProvider')]
    public function test_it_converts_decimal_amount_text_to_minor_units(string $amount, int $expectedMinorUnits): void
    {
        $dto = InvestorCsvRowDTO::fromCsvData([
            'investor_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => '37',
            'investment_amount' => $amount,
            'investment_date' => '2026-07-03',
        ]);

        $this->assertInstanceOf(InvestorCsvRowDTO::class, $dto);
        $this->assertSame($expectedMinorUnits, $dto->amountMinor);
    }

    /**
     * @param array{
     *     investor_id?: string|null,
     *     name?: string|null,
     *     age?: string|null,
     *     investment_amount?: string|null,
     *     investment_date?: string|null
     * } $data
     */
    #[DataProvider('invalidCsvDataProvider')]
    public function test_it_rejects_invalid_csv_data(array $data): void
    {
        $this->assertNull(InvestorCsvRowDTO::fromCsvData($data));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validAmountProvider(): array
    {
        return [
            'two decimal places' => ['1250.50', 125050],
            'whole units' => ['1250', 125000],
            'ninety nine minor units' => ['0.99', 99],
            'one minor unit' => ['0.01', 1],
            'zero' => ['0', 0],
        ];
    }

    /**
     * @return array<string, array<int, array{
     *     investor_id?: string|null,
     *     name?: string|null,
     *     age?: string|null,
     *     investment_amount?: string|null,
     *     investment_date?: string|null
     * }>>
     */
    public static function invalidCsvDataProvider(): array
    {
        $valid = [
            'investor_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => '37',
            'investment_amount' => '1250.50',
            'investment_date' => '2026-07-03',
        ];

        return [
            'missing investor id' => [array_replace($valid, ['investor_id' => ''])],
            'missing name' => [array_replace($valid, ['name' => ''])],
            'non-integer age' => [array_replace($valid, ['age' => '37.5'])],
            'negative age' => [array_replace($valid, ['age' => '-1'])],
            'missing amount' => [array_replace($valid, ['investment_amount' => ''])],
            'negative amount' => [array_replace($valid, ['investment_amount' => '-1'])],
            'too many decimal places' => [array_replace($valid, ['investment_amount' => '1250.999'])],
            'invalid date format' => [array_replace($valid, ['investment_date' => '03/07/2026'])],
            'invalid calendar date' => [array_replace($valid, ['investment_date' => '2026-02-31'])],
        ];
    }
}
