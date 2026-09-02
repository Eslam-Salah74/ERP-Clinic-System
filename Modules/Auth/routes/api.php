<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\Role\RoleController;
use Modules\Auth\Http\Controllers\Api\Staff\StaffController;
use Modules\Auth\Http\Controllers\Api\Permission\PermissionController;
use Modules\Auth\Http\Controllers\AuthController;



Route::prefix('v1/auth')->group(function () {

    Route::post('login', [AuthController::class, 'login']);


    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('staff', StaffController::class);

       
        Route::get('permissions', [PermissionController::class, 'index']);
    });
});

