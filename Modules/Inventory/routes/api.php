<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\Item\ItemController;
use Modules\Inventory\Http\Controllers\Api\PurchaseInvoice\PurchaseInvoiceController;
use Modules\Inventory\Http\Controllers\Api\Supplier\SupplierController;
use Modules\Inventory\Http\Controllers\InventoryController;


Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class);
});
