<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvestorResource;
use App\Models\Investor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvestorController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(): AnonymousResourceCollection
    {
        $investors = Investor::query()
            ->withCount('investments')
            ->withSum('investments', 'amount_minor')
            ->paginate(100);

        return InvestorResource::collection($investors);
    }
}
