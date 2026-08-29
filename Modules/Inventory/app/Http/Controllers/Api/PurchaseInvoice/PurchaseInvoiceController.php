<?php

namespace Modules\Inventory\Http\Controllers\Api\PurchaseInvoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Inventory\Services\PurchaseInvoice\PurchaseInvoiceService;
use Modules\Inventory\Filters\PurchaseInvoice\PurchaseInvoiceFilter;
use Modules\Inventory\Http\Requests\PurchaseInvoice\StorePurchaseInvoiceRequest;
use Modules\Inventory\Http\Requests\PurchaseInvoice\UpdatePurchaseInvoiceRequest;

class PurchaseInvoiceController extends Controller implements HasMiddleware
{
    protected $purchaseInvoice;

    public function __construct(PurchaseInvoiceService $purchaseInvoice)
    {
        $this->purchaseInvoice = $purchaseInvoice;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read purchase_invoices', only: ['index']),
            new Middleware('permission:show purchase_invoices', only: ['show']),
            new Middleware('permission:create purchase_invoices', only: ['store']),
            new Middleware('permission:update purchase_invoices', only: ['update']),
            new Middleware('permission:delete purchase_invoices', only: ['destroy']),
        ];
    }

    public function index(Request $request, PurchaseInvoiceFilter $filter)
    {
        return $this->purchaseInvoice->index($request, $filter);
    }

    public function store(StorePurchaseInvoiceRequest $request)
    {
        return $this->purchaseInvoice->store($request);
    }

    public function show($purchaseInvoice)
    {
        return $this->purchaseInvoice->show($purchaseInvoice);
    }

    public function update($purchaseInvoice, UpdatePurchaseInvoiceRequest $request)
    {
        return $this->purchaseInvoice->update($purchaseInvoice, $request);
    }

    public function destroy($purchaseInvoice)
    {
        return $this->purchaseInvoice->destroy($purchaseInvoice);
    }
}