<?php

namespace App\Http\Resources;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'investor_id' => $this->external_id,
            'name' => $this->name,
            'age' => $this->age,
            'total_invested' => MoneyFormatter::formatMinorAmount((int) ($this->investments_sum_amount_minor ?? 0)),
            'investment_count' => (int) ($this->investments_count ?? 0),
        ];
    }
}
