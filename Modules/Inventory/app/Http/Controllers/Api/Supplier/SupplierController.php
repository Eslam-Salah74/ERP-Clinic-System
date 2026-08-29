<?php

namespace Modules\Inventory\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Inventory\Services\Supplier\SupplierService;
use Modules\Inventory\Filters\Supplier\SupplierFilter;
use Modules\Inventory\Http\Requests\Supplier\StoreSupplierRequest;
use Modules\Inventory\Http\Requests\Supplier\UpdateSupplierRequest;

class SupplierController extends Controller implements HasMiddleware
{
    protected $supplier;

    public function __construct(SupplierService $supplier)
    {
        $this->supplier = $supplier;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read suppliers', only: ['index']),
            new Middleware('permission:show suppliers', only: ['show']),
            new Middleware('permission:create suppliers', only: ['store']),
            new Middleware('permission:update suppliers', only: ['update']),
            new Middleware('permission:delete suppliers', only: ['destroy']),
        ];
    }

    public function index(Request $request, SupplierFilter $filter)
    {
        return $this->supplier->index($request, $filter);
    }

    public function store(StoreSupplierRequest $request)
    {
        return $this->supplier->store($request);
    }

    public function show($supplier)
    {
        return $this->supplier->show($supplier);
    }

    public function update($supplier, UpdateSupplierRequest $request)
    {
        return $this->supplier->update($supplier, $request);
    }

    public function destroy($supplier)
    {
        return $this->supplier->destroy($supplier);
    }
}