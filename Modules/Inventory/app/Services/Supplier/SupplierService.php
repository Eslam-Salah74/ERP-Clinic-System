<?php

namespace Modules\Inventory\Services\Supplier;

use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Filters\Supplier\SupplierFilter;
use Modules\Inventory\Http\Resources\Supplier\SupplierResource;
use App\Support\API;

class SupplierService
{
    public function index($request, SupplierFilter $filter)
    {
        $data = Supplier::filter($filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(SupplierResource::collection($data))->build();
    }

    public function store($request)
    {
        $data = Supplier::create($request->validated());
        return API::newInstance()->isCreated('Created successfully')->setData(new SupplierResource($data))->build();
    }

    public function show($id)
    {
        $record = Supplier::find($id);
        if (!$record) return API::newInstance()->isError('Record not found')->build();
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new SupplierResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Supplier::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new SupplierResource($record))->build();
    }

    public function destroy($id)
    {
        $record = Supplier::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
