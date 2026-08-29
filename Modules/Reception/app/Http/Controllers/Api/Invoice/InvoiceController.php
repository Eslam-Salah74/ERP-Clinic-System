<?php

namespace Modules\Reception\Http\Controllers\Api\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Reception\Filters\Invoice\InvoiceFilter;
use Modules\Reception\Http\Requests\Invoice\RefundInvoiceRequest;
use Modules\Reception\Http\Requests\Invoice\StoreInvoiceRequest;
use Modules\Reception\Http\Requests\Invoice\UpdateInvoiceRequest;
use Modules\Reception\Services\Invoice\InvoiceService;

class InvoiceController extends Controller implements HasMiddleware
{
    protected $invoice;

    public function __construct(InvoiceService $invoice)
    {
        $this->invoice = $invoice;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read invoices', only: ['index']),
            new Middleware('permission:show invoices', only: ['show']),
            new Middleware('permission:create invoices', only: ['store']),
            new Middleware('permission:update invoices', only: ['update']),
            new Middleware('permission:delete invoices', only: ['destroy']),
        ];
    }

    public function index(Request $request, InvoiceFilter $filter)
    {
        return $this->invoice->index($request, $filter);
    }

    public function store(StoreInvoiceRequest $request)
    {
        return $this->invoice->store($request);
    }

    public function show($invoice)
    {
        return $this->invoice->show($invoice);
    }

    public function update($invoice, UpdateInvoiceRequest $request)
    {
        return $this->invoice->update($invoice, $request);
    }

    public function destroy($invoice)
    {
        return $this->invoice->destroy($invoice);
    }

    public function refund($id, RefundInvoiceRequest $request)
    {
        return $this->invoice->refund($id, $request);
    }
}
