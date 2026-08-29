<?php

use Illuminate\Support\Facades\Route;
use Modules\Setup\Http\Controllers\Api\Department\DepartmentController;
use Modules\Setup\Http\Controllers\Api\Service\ServiceController;
use Modules\Setup\Http\Controllers\Api\Setting\SettingController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('settings', SettingController::class);

});
