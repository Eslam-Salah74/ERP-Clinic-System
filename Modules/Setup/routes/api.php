<?php

use Illuminate\Support\Facades\Route;
use Modules\Setup\Http\Controllers\Api\Department\DepartmentController;
use Modules\Setup\Http\Controllers\Api\Notification\NotificationController;
use Modules\Setup\Http\Controllers\Api\Service\ServiceController;
use Modules\Setup\Http\Controllers\Api\Setting\SettingController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('settings', SettingController::class);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('notifications/check', [NotificationController::class, 'check']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
});
