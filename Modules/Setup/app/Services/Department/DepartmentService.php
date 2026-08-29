<?php

namespace Modules\Setup\Services\Department;

use Modules\Setup\Models\Department;
use Modules\Setup\Filters\Department\DepartmentFilter;
use Modules\Setup\Http\Resources\Department\DepartmentResource;
use App\Support\API;

class DepartmentService
{
    public function index($request, DepartmentFilter $filter)
    {
        $data = Department::filter($filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(DepartmentResource::collection($data))->build();
    }

    public function store($request)
    {
        $data = Department::create($request->validated());
        return API::newInstance()->isCreated('Created successfully')->setData(new DepartmentResource($data))->build();
    }

    public function show($id)
    {
        $record = Department::find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new DepartmentResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Department::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new DepartmentResource($record))->build();
    }

    public function destroy($id)
    {
        $record = Department::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}