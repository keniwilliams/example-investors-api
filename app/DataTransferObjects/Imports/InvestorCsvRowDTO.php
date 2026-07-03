<?php

namespace App\DataTransferObjects\Imports;

use DateTimeImmutable;

final readonly class InvestorCsvRowDTO
{
    private function __construct(
        public string $externalId,
        public string $name,
        public int $age,
        public int $amountMinor,
        public string $investmentDate,
    ) {}

    /**
     * @param array{
     *     investor_id?: string|null,
     *     name?: string|null,
     *     age?: string|null,
     *     investment_amount?: string|null,
     *     investment_date?: string|null
     * } $data
     */
    public static function fromCsvData(array $data): ?self
    {
        $externalId = self::normaliseString($data['investor_id'] ?? null);
        $name = self::normaliseString($data['name'] ?? null);
        $age = self::normaliseAge($data['age'] ?? null);
        $amountMinor = self::normaliseAmountMinor($data['investment_amount'] ?? null);
        $investmentDate = self::normaliseDate($data['investment_date'] ?? null);

        if (
            $externalId === null
            || $name === null
            || $age === null
            || $amountMinor === null
            || $investmentDate === null
        ) {
            return null;
        }

        return new self(
            externalId: $externalId,
            name: $name,
            age: $age,
            amountMinor: $amountMinor,
            investmentDate: $investmentDate,
        );
    }

    /**
     * @return array{
     *     external_id: string,
     *     name: string,
     *     age: int
     * }
     */
    public function investorPayload(): array
    {
        return [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'age' => $this->age,
        ];
    }

    /**
     * @return array{
     *     amount_minor: int,
     *     investment_date: string
     * }
     */
    public function investmentPayload(): array
    {
        return [
            'amount_minor' => $this->amountMinor,
            'investment_date' => $this->investmentDate,
        ];
    }

    public function investmentNaturalKey(): string
    {
        return $this->externalId.'|'.$this->investmentDate;
    }

    private static function normaliseString(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private static function normaliseAge(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $age = (int) $value;

        if ($age < 0) {
            return null;
        }

        return $age;
    }

    private static function normaliseAmountMinor(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '00');

        $decimal = str_pad($decimal, 2, '0');

        return ((int) $whole * 100) + (int) $decimal;
    }

    private static function normaliseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
