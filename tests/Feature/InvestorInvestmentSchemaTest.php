<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\Investor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestorInvestmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_has_many_investments(): void
    {
        $investor = Investor::create([
            'external_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 37,
        ]);

        $investment = $investor->investments()->create([
            'amount' => 1250.50,
            'investment_date' => '2026-07-03',
        ]);

        $this->assertTrue($investor->investments->contains($investment));
        $this->assertTrue($investment->investor->is($investor));
        $this->assertSame('2026-07-03', $investment->investment_date->toDateString());
        $this->assertDatabaseHas('investors', [
            'external_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 37,
        ]);
        $this->assertDatabaseHas('investments', [
            'investor_id' => $investor->id,
        ]);
    }

    public function test_duplicate_investments_for_same_investor_and_date_are_prevented(): void
    {
        $investor = Investor::create([
            'external_id' => 'INV-002',
            'name' => 'Grace Hopper',
            'age' => 85,
        ]);

        Investment::create([
            'investor_id' => $investor->id,
            'amount' => 500.00,
            'investment_date' => '2026-07-03',
        ]);

        $this->expectException(QueryException::class);

        Investment::create([
            'investor_id' => $investor->id,
            'amount' => 750.00,
            'investment_date' => '2026-07-03',
        ]);
    }
}
