<?php

use App\Http\Controllers\Api\InvestmentAverageAmountController;
use App\Http\Controllers\Api\InvestmentCountController;
use App\Http\Controllers\Api\InvestorAverageAgeController;
use App\Http\Controllers\Api\InvestorController;
use App\Http\Controllers\Api\InvestorImportController;
use Illuminate\Support\Facades\Route;

Route::post('/imports/investors', InvestorImportController::class);

Route::get('/investors', [InvestorController::class, 'index']);
Route::get('/investors/average-age', InvestorAverageAgeController::class);
Route::get('/investments/average-amount', InvestmentAverageAmountController::class);
Route::get('/investments/count', InvestmentCountController::class);
