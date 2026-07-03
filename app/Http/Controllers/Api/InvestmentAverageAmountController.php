<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Investors\InvestorAggregateService;
use App\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;

class InvestmentAverageAmountController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(InvestorAggregateService $aggregateService): JsonResponse
    {
        return response()->json([
            'average_investment_amount' => MoneyFormatter::formatMinorAmount(
                $aggregateService->averageInvestmentAmountMinor(),
            ),
        ]);
    }
}
