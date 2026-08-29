<?php

use Illuminate\Support\Facades\Route;
use Modules\Reception\Http\Controllers\ReceptionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('receptions', ReceptionController::class)->names('reception');
});
