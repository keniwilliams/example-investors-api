<?php

namespace App\Services\Imports;

use App\Models\Investor;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class InvestorCsvImportService
{
    private const CHUNK_SIZE = 500;

    /**
     * @var array<int, string>
     */
    private const EXPECTED_HEADERS = [
        'investor_id',
        'name',
        'age',
        'investment_amount',
        'investment_date',
    ];

    /**
     * @return array{status: string, rows_read: int, investors_upserted: int, investments_upserted: int, rows_skipped: int}
     */
    public function import(string $path): array
    {
        $summary = [
            'status' => 'completed',
            'rows_read' => 0,
            'investors_upserted' => 0,
            'investments_upserted' => 0,
            'rows_skipped' => 0,
        ];

        $seenInvestors = [];
        $seenInvestments = [];
        $chunk = [];

        try {
            $file = $this->openCsv($path);
            $this->assertExpectedHeaders($file->fgetcsv());

            while (! $file->eof()) {
                $row = $file->fgetcsv();

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $summary['rows_read']++;
                $mappedRow = $this->mapRow($row);

                if ($mappedRow === null) {
                    $summary['rows_skipped']++;

                    continue;
                }

                $seenInvestors[$mappedRow['external_id']] = true;
                $seenInvestments[$mappedRow['external_id'].'|'.$mappedRow['investment_date']] = true;
                $chunk[] = $mappedRow;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $this->flushChunk($chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->flushChunk($chunk);
            }

            $summary['investors_upserted'] = count($seenInvestors);
            $summary['investments_upserted'] = count($seenInvestments);

            return $summary;
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    private function openCsv(string $path): SplFileObject
    {
        $file = new SplFileObject(Storage::disk('local')->path($path));
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        return $file;
    }

    /**
     * @param  array<int, string|null>|false  $headers
     *
     * @throws ValidationException
     */
    private function assertExpectedHeaders(array|false $headers): void
    {
        if ($headers === false) {
            throw $this->invalidHeaderException();
        }

        $normalizedHeaders = array_map(
            fn (?string $header): string => trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B"),
            $headers,
        );

        if ($normalizedHeaders !== self::EXPECTED_HEADERS) {
            throw $this->invalidHeaderException();
        }
    }

    /**
     * @param  array<int, string|null>|false  $row
     */
    private function isEmptyRow(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }

        return count(array_filter($row, fn (?string $value): bool => trim((string) $value) !== '')) === 0;
    }

    /**
     * @param  array<int, string|null>|false  $row
     * @return array{external_id: string, name: string, age: int, amount: string, investment_date: string}|null
     */
    private function mapRow(array|false $row): ?array
    {
        if ($row === false || count($row) !== count(self::EXPECTED_HEADERS)) {
            return null;
        }

        $data = array_combine(self::EXPECTED_HEADERS, array_map(
            fn (?string $value): string => trim((string) $value),
            $row,
        ));

        if ($data === false) {
            return null;
        }

        if (
            $data['investor_id'] === ''
            || $data['name'] === ''
            || ! ctype_digit($data['age'])
            || ! is_numeric($data['investment_amount'])
        ) {
            return null;
        }

        $age = (int) $data['age'];
        $amount = (float) $data['investment_amount'];
        $investmentDate = DateTimeImmutable::createFromFormat('!Y-m-d', $data['investment_date']);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $age < 0
            || $amount < 0
            || $investmentDate === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            return null;
        }

        return [
            'external_id' => $data['investor_id'],
            'name' => $data['name'],
            'age' => $age,
            'amount' => number_format($amount, 2, '.', ''),
            'investment_date' => $investmentDate->format('Y-m-d'),
        ];
    }

    /**
     * @param  array<int, array{external_id: string, name: string, age: int, amount: string, investment_date: string}>  $rows
     */
    private function flushChunk(array $rows): void
    {
        $now = now();
        $investors = [];

        foreach ($rows as $row) {
            $investors[$row['external_id']] = [
                'external_id' => $row['external_id'],
                'name' => $row['name'],
                'age' => $row['age'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('investors')->upsert(
            array_values($investors),
            ['external_id'],
            ['name', 'age', 'updated_at'],
        );

        $investorIds = Investor::query()
            ->whereIn('external_id', array_keys($investors))
            ->pluck('id', 'external_id');

        $investments = [];

        foreach ($rows as $row) {
            $investorId = $investorIds[$row['external_id']] ?? null;

            if ($investorId === null) {
                continue;
            }

            $investments[$investorId.'|'.$row['investment_date']] = [
                'investor_id' => $investorId,
                'amount' => $row['amount'],
                'investment_date' => DateTimeImmutable::createFromFormat('!Y-m-d', $row['investment_date']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($investments === []) {
            return;
        }

        DB::table('investments')->upsert(
            array_values($investments),
            ['investor_id', 'investment_date'],
            ['amount', 'updated_at'],
        );
    }

    private function invalidHeaderException(): ValidationException
    {
        return ValidationException::withMessages([
            'file' => 'The CSV headers must be: '.implode(',', self::EXPECTED_HEADERS).'.',
        ]);
    }
}
