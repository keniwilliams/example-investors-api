<?php

namespace Tests\Feature;

use App\Services\InvestorCsvImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class InvestorImportUploadTest extends TestCase
{
    public function test_missing_file_returns_validation_json(): void
    {
        $response = $this->postJson('/api/imports/investors');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_non_csv_file_returns_validation_json(): void
    {
        Storage::fake('local');

        $response = $this->postJson('/api/imports/investors', [
            'file' => UploadedFile::fake()->create('investors.pdf', 1, 'application/pdf'),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_csv_upload_is_stored_privately_and_delegated_to_import_service(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $summary = [
            'processed_rows' => 3,
            'imported_rows' => 2,
            'skipped_rows' => 1,
            'error_rows' => 0,
        ];

        $this->mock(InvestorCsvImportService::class, function (MockInterface $mock) use ($summary): void {
            $mock->shouldReceive('import')
                ->once()
                ->withArgs(function (string $path): bool {
                    $this->assertStringStartsWith('uploads/csv/', $path);
                    Storage::disk('local')->assertExists($path);
                    Storage::disk('public')->assertMissing($path);

                    return true;
                })
                ->andReturn($summary);
        });

        $response = $this->postJson('/api/imports/investors', [
            'file' => UploadedFile::fake()->createWithContent(
                'investors.csv',
                "investor_id,name,age,investment_amount,investment_date\nINV-1,Ada,37,1250.50,2026-07-03\n",
            ),
        ]);

        $response
            ->assertOk()
            ->assertExactJson($summary);
    }
}
