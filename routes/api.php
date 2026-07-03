<?php

use App\Http\Controllers\Api\InvestorImportController;
use Illuminate\Support\Facades\Route;

Route::post('/imports/investors', InvestorImportController::class);
