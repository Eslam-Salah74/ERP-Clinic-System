<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

Route::get('/', function () {
    return view('welcome');
});


// ==========================================
// الأوامر الأساسية للمشروع
// ==========================================
Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:cache');
    Artisan::call('route:clear');
    Artisan::call('migrate');
    Artisan::call('permission:cache-reset');
    return "Cache Cleared Successfully";
});

Route::get('/migrate', function () {
    Artisan::call('migrate');
    return "Migrate Done Successfully";
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return "Storage link created successfully.";
});

Route::get('/db-seed', function () {
    Artisan::call('db:seed');
    return "DB Seed Done Successfully";
});


Route::get('/seed', function () {
    $modules = Module::all();

    if (empty($modules)) {
        return "No modules found.";
    }

    $seededCount = 0;

    foreach ($modules as $module) {
        if ($module->isEnabled()) {
            Artisan::call('module:seed', ['module' => $module->getName()]);
            $seededCount++;
        }
    }

    return "Successfully seeded {$seededCount} active modules!";
});

Route::get('/module-refresh/{moduleName}', function ($moduleName) {
    Artisan::call('module:disable', ['module' => $moduleName]);
    Artisan::call('module:enable', ['module' => $moduleName]);

    return "Module {$moduleName} refreshed successfully";
});
