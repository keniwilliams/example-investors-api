<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Investors\InvestorAggregateService;
use Illuminate\Http\JsonResponse;

class InvestorAverageAgeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(InvestorAggregateService $aggregateService): JsonResponse
    {
        return response()->json([
            'average_age' => round($aggregateService->averageInvestorAge(), 2),
        ]);
    }
}
