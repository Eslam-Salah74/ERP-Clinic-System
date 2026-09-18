<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\Api\Expense\ExpenseController;
use Modules\HR\Http\Controllers\Api\Contract\ContractController;
use Modules\HR\Http\Controllers\Api\Payroll\PayrollController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    // المصروفات والنفقات
    Route::get('expenses/summary', [ExpenseController::class, 'summary']);
    Route::apiResource('expenses', ExpenseController::class);

    // عقود الموظفين والعمولات (Staff Contracts)
    Route::get('contracts/user/{userId}', [ContractController::class, 'getActiveContractByUser']);
    Route::apiResource('contracts', ContractController::class);

    // مسيرات الرواتب (Payroll System)
    Route::post('payrolls/generate', [PayrollController::class, 'generate']);
    Route::post('payrolls/generate-all', [PayrollController::class, 'generateAll']);
    Route::post('payrolls/{id}/approve', [PayrollController::class, 'approve']);
    Route::post('payrolls/{id}/pay', [PayrollController::class, 'pay']);
    Route::apiResource('payrolls', PayrollController::class);
});
