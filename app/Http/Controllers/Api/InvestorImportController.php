<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportInvestorCsvRequest;
use App\Services\InvestorCsvImportService;
use Illuminate\Http\JsonResponse;

class InvestorImportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ImportInvestorCsvRequest $request,
        InvestorCsvImportService $importService,
    ): JsonResponse {
        $path = $request->file('file')->store('uploads/csv', 'local');

        return response()->json($importService->import($path));
    }
}
