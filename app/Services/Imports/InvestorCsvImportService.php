<?php

namespace App\Services\Imports;

use App\DataTransferObjects\Imports\InvestorCsvRowDTO;
use App\Models\Investor;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class InvestorCsvImportService
{
    private const int CHUNK_SIZE = 500;

    /**
     * @var array<int, string>
     */
    private const array EXPECTED_HEADERS = [
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
            'investors_upserted' => 0, // immediately overwritten but stated here for visibility
            'investments_upserted' => 0, // immediately overwritten but stated here for visibility
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
                $rowDTO = $this->mapRow($row);

                if ($rowDTO === null) {
                    $summary['rows_skipped']++;

                    continue;
                }

                $seenInvestors[$rowDTO->externalId] = true;
                $seenInvestments[$rowDTO->investmentNaturalKey()] = true;
                $chunk[] = $rowDTO;

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
        $file->setCsvControl(',', '"', '\\');
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
     */
    private function mapRow(array|false $row): ?InvestorCsvRowDTO
    {
        if ($row === false || count($row) !== count(self::EXPECTED_HEADERS)) {
            return null;
        }

        $data = array_combine(self::EXPECTED_HEADERS, array_map(
            fn (?string $value): string => trim((string) $value),
            $row,
        ));

        if (! $data) {
            return null;
        }

        return InvestorCsvRowDTO::fromCsvData($data);
    }

    /**
     * @param  array<int, InvestorCsvRowDTO>  $rows
     */
    private function flushChunk(array $rows): void
    {
        $now = now();
        $investors = [];

        foreach ($rows as $rowDTO) {
            $investors[$rowDTO->externalId] = [
                ...$rowDTO->investorPayload(),
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

        foreach ($rows as $rowDTO) {
            $investorId = $investorIds[$rowDTO->externalId] ?? null;

            if ($investorId === null) {
                continue;
            }

            $investments[$investorId.'|'.$rowDTO->investmentDate] = [
                'investor_id' => $investorId,
                ...$rowDTO->investmentPayload(),
                'investment_date' => DateTimeImmutable::createFromFormat('!Y-m-d', $rowDTO->investmentDate),
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
            ['amount_minor', 'updated_at'],
        );
    }

    private function invalidHeaderException(): ValidationException
    {
        return ValidationException::withMessages([
            'file' => 'The CSV headers must be: '.implode(',', self::EXPECTED_HEADERS).'.',
        ]);
    }
}
