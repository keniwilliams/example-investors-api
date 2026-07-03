<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Investor;
use App\Services\Imports\InvestorCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvestorCsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_streams_and_imports_valid_csv_rows_in_chunks(): void
    {
        Storage::fake('local');

        $path = $this->storeCsv(
            collect(range(1, 501))
                ->map(fn (int $number): string => sprintf(
                    'INV-%03d,Investor %03d,%d,%d.50,2026-07-%02d',
                    $number,
                    $number,
                    20 + ($number % 60),
                    1000 + $number,
                    (($number - 1) % 28) + 1,
                ))
                ->prepend('investor_id,name,age,investment_amount,investment_date')
                ->implode("\n"),
        );

        $summary = app(InvestorCsvImportService::class)->import($path);

        $this->assertSame([
            'status' => 'completed',
            'rows_read' => 501,
            'investors_upserted' => 501,
            'investments_upserted' => 501,
            'rows_skipped' => 0,
        ], $summary);
        $this->assertSame(501, Investor::count());
        $this->assertSame(501, Investment::count());
        $this->assertDatabaseHas('investments', [
            'amount_minor' => 100150,
        ]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_it_updates_existing_investor_and_investment_records(): void
    {
        Storage::fake('local');

        $investor = Investor::create([
            'external_id' => 'INV-001',
            'name' => 'Old Name',
            'age' => 40,
        ]);

        Investment::create([
            'investor_id' => $investor->id,
            'amount_minor' => 10000,
            'investment_date' => '2026-07-03',
        ]);

        $path = $this->storeCsv(implode("\n", [
            'investor_id,name,age,investment_amount,investment_date',
            'INV-001,Updated Name,41,250.75,2026-07-03',
        ]));

        $summary = app(InvestorCsvImportService::class)->import($path);

        $this->assertSame([
            'status' => 'completed',
            'rows_read' => 1,
            'investors_upserted' => 1,
            'investments_upserted' => 1,
            'rows_skipped' => 0,
        ], $summary);
        $this->assertSame(1, Investor::count());
        $this->assertSame(1, Investment::count());
        $this->assertDatabaseHas('investors', [
            'external_id' => 'INV-001',
            'name' => 'Updated Name',
            'age' => 41,
        ]);
        $this->assertDatabaseHas('investments', [
            'investor_id' => $investor->id,
            'amount_minor' => 25075,
        ]);
        $this->assertSame('2026-07-03', Investment::first()->investment_date->toDateString());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_it_skips_invalid_rows_and_imports_valid_rows(): void
    {
        Storage::fake('local');

        $path = $this->storeCsv(implode("\n", [
            'investor_id,name,age,investment_amount,investment_date',
            'INV-001,Ada Lovelace,37,1250.50,2026-07-03',
            ',Missing Id,37,1250.50,2026-07-03',
            'INV-002,Invalid Age,nope,1250.50,2026-07-03',
            'INV-003,Invalid Amount,37,-1,2026-07-03',
            'INV-004,Invalid Date,37,1250.50,03/07/2026',
            'INV-005,Too Many Decimal Places,37,12.999,2026-07-03',
        ]));

        $summary = app(InvestorCsvImportService::class)->import($path);

        $this->assertSame([
            'status' => 'completed',
            'rows_read' => 6,
            'investors_upserted' => 1,
            'investments_upserted' => 1,
            'rows_skipped' => 5,
        ], $summary);
        $this->assertSame(1, Investor::count());
        $this->assertSame(1, Investment::count());
        $this->assertDatabaseHas('investors', [
            'external_id' => 'INV-001',
        ]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_it_rejects_invalid_headers_and_cleans_up_upload(): void
    {
        Storage::fake('local');

        $path = $this->storeCsv(implode("\n", [
            'external_id,name,age,investment_amount,investment_date',
            'INV-001,Ada Lovelace,37,1250.50,2026-07-03',
        ]));

        try {
            app(InvestorCsvImportService::class)->import($path);
            $this->fail('Expected invalid headers to throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
            Storage::disk('local')->assertMissing($path);
        }
    }

    private function storeCsv(string $contents): string
    {
        $path = 'uploads/csv/test-import.csv';

        Storage::disk('local')->put($path, $contents);

        return $path;
    }
}
