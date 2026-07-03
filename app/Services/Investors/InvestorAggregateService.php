<?php

namespace App\Services\Investors;

use App\Models\Investment;
use App\Models\Investor;

class InvestorAggregateService
{
    public function averageInvestorAge(): float
    {
        return (float) (Investor::query()->avg('age') ?? 0);
    }

    public function averageInvestmentAmount(): float
    {
        return (float) (Investment::query()->avg('amount') ?? 0);
    }

    public function totalInvestments(): int
    {
        return Investment::query()->count();
    }
}
