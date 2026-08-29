<?php

use Illuminate\Support\Facades\Route;
use Modules\Setup\Http\Controllers\SetupController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('setups', SetupController::class)->names('setup');
});
