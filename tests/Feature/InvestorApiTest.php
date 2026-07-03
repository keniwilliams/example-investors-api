<?php

namespace Tests\Feature;

use App\Models\Investor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_investors_index_returns_paginated_unique_investors(): void
    {
        $investor = Investor::create([
            'external_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 37,
        ]);

        $investor->investments()->create([
            'amount_minor' => 125000,
            'investment_date' => '2026-07-01',
        ]);

        $investor->investments()->create([
            'amount_minor' => 50,
            'investment_date' => '2026-07-02',
        ]);

        Investor::create([
            'external_id' => 'INV-002',
            'name' => 'Grace Hopper',
            'age' => 60,
        ]);

        $response = $this->getJson('/api/investors');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['investor_id', 'name', 'age', 'total_invested', 'investment_count'],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_investors_index_formats_total_invested_and_investment_count(): void
    {
        $investor = Investor::create([
            'external_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 37,
        ]);

        $investor->investments()->create([
            'amount_minor' => 125000,
            'investment_date' => '2026-07-01',
        ]);

        $investor->investments()->create([
            'amount_minor' => 50,
            'investment_date' => '2026-07-02',
        ]);

        $response = $this->getJson('/api/investors');

        $response->assertOk();
        $response->assertJsonFragment([
            'investor_id' => 'INV-001',
            'name' => 'Ada Lovelace',
            'age' => 37,
            'total_invested' => '1250.50',
            'investment_count' => 2,
        ]);
    }

    public function test_investors_index_reports_zero_values_for_investor_with_no_investments(): void
    {
        Investor::create([
            'external_id' => 'INV-003',
            'name' => 'Alan Turing',
            'age' => 41,
        ]);

        $response = $this->getJson('/api/investors');

        $response->assertOk();
        $response->assertJsonFragment([
            'investor_id' => 'INV-003',
            'total_invested' => '0.00',
            'investment_count' => 0,
        ]);
    }

    public function test_average_age_endpoint_returns_rounded_average(): void
    {
        Investor::create(['external_id' => 'INV-001', 'name' => 'Ada Lovelace', 'age' => 30]);
        Investor::create(['external_id' => 'INV-002', 'name' => 'Grace Hopper', 'age' => 60]);
        Investor::create(['external_id' => 'INV-003', 'name' => 'Alan Turing', 'age' => 41]);

        $response = $this->getJson('/api/investors/average-age');

        $response->assertOk();
        $response->assertJson(['average_age' => 43.67]);
    }

    public function test_average_age_endpoint_returns_zero_for_empty_database(): void
    {
        $response = $this->getJson('/api/investors/average-age');

        $response->assertOk();
        $response->assertJson(['average_age' => 0]);
    }

    public function test_average_investment_amount_endpoint_returns_fixed_two_decimal_string(): void
    {
        $investor = Investor::create(['external_id' => 'INV-001', 'name' => 'Ada Lovelace', 'age' => 37]);

        $investor->investments()->create(['amount_minor' => 125000, 'investment_date' => '2026-07-01']);
        $investor->investments()->create(['amount_minor' => 9, 'investment_date' => '2026-07-02']);

        $response = $this->getJson('/api/investments/average-amount');

        $response->assertOk();
        $response->assertExactJson(['average_investment_amount' => '625.05']);
    }

    public function test_average_investment_amount_endpoint_returns_zero_string_for_empty_database(): void
    {
        $response = $this->getJson('/api/investments/average-amount');

        $response->assertOk();
        $response->assertExactJson(['average_investment_amount' => '0.00']);
    }

    public function test_investments_count_endpoint_returns_total_investment_count(): void
    {
        $investor = Investor::create(['external_id' => 'INV-001', 'name' => 'Ada Lovelace', 'age' => 37]);

        $investor->investments()->create(['amount_minor' => 125000, 'investment_date' => '2026-07-01']);
        $investor->investments()->create(['amount_minor' => 50, 'investment_date' => '2026-07-02']);

        $response = $this->getJson('/api/investments/count');

        $response->assertOk();
        $response->assertExactJson(['total_investments' => 2]);
    }

    public function test_investments_count_endpoint_returns_zero_for_empty_database(): void
    {
        $response = $this->getJson('/api/investments/count');

        $response->assertOk();
        $response->assertExactJson(['total_investments' => 0]);
    }
}
