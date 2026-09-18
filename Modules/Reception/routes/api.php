<?php

use Illuminate\Support\Facades\Route;
use Modules\Reception\Http\Controllers\Api\Appointment\AppointmentController;
use Modules\Reception\Http\Controllers\Api\FollowUp\FollowUpController;
use Modules\Reception\Http\Controllers\Api\Invoice\InvoiceController;
use Modules\Reception\Http\Controllers\Api\Patient\PatientController;
use Modules\Reception\Http\Controllers\Api\Shift\ShiftController;


Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('appointments', AppointmentController::class);
    Route::patch('appointments/{id}/status', [AppointmentController::class, 'changeStatus']);

    Route::post('shifts/open', [ShiftController::class, 'open']);
    Route::post('shifts/{id}/close', [ShiftController::class, 'close']);

    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{id}/refund', [InvoiceController::class, 'refund']);
    Route::post('invoices/{id}/pay', [InvoiceController::class, 'payRemaining']);

    Route::apiResource('follow-ups', FollowUpController::class);
    Route::patch('follow-ups/{id}/status', [FollowUpController::class, 'changeStatus']);
});
