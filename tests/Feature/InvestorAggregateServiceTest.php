<?php

namespace Tests\Feature;

use App\Models\Investor;
use App\Services\Investors\InvestorAggregateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestorAggregateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_zero_values_when_there_is_no_data(): void
    {
        $service = app(InvestorAggregateService::class);

        $this->assertSame(0.0, $service->averageInvestorAge());
        $this->assertSame(0, $service->averageInvestmentAmountMinor());
        $this->assertSame(0, $service->totalInvestments());
    }

    public function test_it_calculates_investor_and_investment_aggregates(): void
    {
        $firstInvestor = Investor::create([
            'external_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 30,
        ]);

        $secondInvestor = Investor::create([
            'external_id' => 'INV-002',
            'name' => 'Grace Hopper',
            'age' => 60,
        ]);

        $firstInvestor->investments()->create([
            'amount_minor' => 10000,
            'investment_date' => '2026-07-01',
        ]);

        $firstInvestor->investments()->create([
            'amount_minor' => 20000,
            'investment_date' => '2026-07-02',
        ]);

        $secondInvestor->investments()->create([
            'amount_minor' => 60000,
            'investment_date' => '2026-07-01',
        ]);

        $service = app(InvestorAggregateService::class);

        $this->assertSame(45.0, $service->averageInvestorAge());
        $this->assertSame(30000, $service->averageInvestmentAmountMinor());
        $this->assertSame(3, $service->totalInvestments());
    }
}
