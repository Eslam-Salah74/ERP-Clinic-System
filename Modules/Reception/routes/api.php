<?php

use Illuminate\Support\Facades\Route;
use Modules\Reception\Http\Controllers\Api\Appointment\AppointmentController;
use Modules\Reception\Http\Controllers\Api\FollowUp\FollowUpController;
use Modules\Reception\Http\Controllers\Api\Invoice\InvoiceController;
use Modules\Reception\Http\Controllers\Api\Patient\PatientController;
use Modules\Reception\Http\Controllers\Api\PatientPackage\PatientPackageController;
use Modules\Reception\Http\Controllers\Api\Shift\ShiftController;


use Modules\Reception\Http\Controllers\Api\Report\ReportController;


Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::get('patients/{id}/profile', [PatientController::class, 'profile']);
    Route::get('patients/{id}/file', [PatientController::class, 'profile']);
    Route::get('patients/{id}/active-packages', [PatientPackageController::class, 'patientActivePackages']);
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('appointments', AppointmentController::class);
    Route::patch('appointments/{id}/status', [AppointmentController::class, 'changeStatus']);

    // منظومة اشتراكات وباقات المرضى والاستهلاك
    Route::get('patient-packages', [PatientPackageController::class, 'index']);
    Route::get('patient-packages/{id}', [PatientPackageController::class, 'show']);
    Route::post('patient-packages/subscribe', [PatientPackageController::class, 'subscribe']);
    Route::post('patient-packages/consume', [PatientPackageController::class, 'consume']);
    Route::post('patient-packages/{id}/pay-debt', [PatientPackageController::class, 'payDebt']);

    Route::post('shifts/open', [ShiftController::class, 'open']);
    Route::post('shifts/{id}/close', [ShiftController::class, 'close']);

    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{id}/refund', [InvoiceController::class, 'refund']);
    Route::post('invoices/{id}/pay', [InvoiceController::class, 'payRemaining']);

    Route::apiResource('follow-ups', FollowUpController::class);
    Route::patch('follow-ups/{id}/status', [FollowUpController::class, 'changeStatus']);

    // منظومة التقارير المالية والإدارية الشاملة
    Route::prefix('reports')->group(function () {
        Route::get('daily-safe', [ReportController::class, 'dailySafe']);
        Route::get('shifts/{id}/safe', [ReportController::class, 'shiftSafe']);
        Route::get('financial-summary', [ReportController::class, 'financialSummary']);
        Route::get('departments', [ReportController::class, 'departments']);
        Route::get('doctors', [ReportController::class, 'doctors']);
        Route::get('devices', [ReportController::class, 'devices']);
        Route::get('expenses-salaries', [ReportController::class, 'expensesAndSalaries']);
        Route::get('transactions-ledger', [ReportController::class, 'transactionsLedger']);
    });
});
