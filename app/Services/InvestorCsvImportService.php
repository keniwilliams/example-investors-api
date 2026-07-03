<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class InvestorCsvImportService
{
    /**
     * @return array{processed_rows: int, imported_rows: int, skipped_rows: int, error_rows: int}
     */
    public function import(string $path): array
    {
        try {
            return [
                'processed_rows' => 0,
                'imported_rows' => 0,
                'skipped_rows' => 0,
                'error_rows' => 0,
            ];
        } finally {
            Storage::disk('local')->delete($path);
        }
    }
}
