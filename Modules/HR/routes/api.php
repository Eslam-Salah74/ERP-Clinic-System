<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\Api\Expense\ExpenseController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::get('expenses/summary', [ExpenseController::class, 'summary']);
    Route::apiResource('expenses', ExpenseController::class);
});
