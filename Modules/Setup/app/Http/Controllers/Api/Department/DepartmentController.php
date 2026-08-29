<?php

namespace Modules\Setup\Http\Controllers\Api\Department;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Setup\Services\Department\DepartmentService;
use Modules\Setup\Filters\Department\DepartmentFilter;
use Modules\Setup\Http\Requests\Department\StoreDepartmentRequest;
use Modules\Setup\Http\Requests\Department\UpdateDepartmentRequest;

class DepartmentController extends Controller implements HasMiddleware
{
    protected $department;

    public function __construct(DepartmentService $department)
    {
        $this->department = $department;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read departments', only: ['index']),
            new Middleware('permission:show departments', only: ['show']),
            new Middleware('permission:create departments', only: ['store']),
            new Middleware('permission:update departments', only: ['update']),
            new Middleware('permission:delete departments', only: ['destroy']),
        ];
    }

    public function index(Request $request, DepartmentFilter $filter)
    {
        return $this->department->index($request, $filter);
    }

    public function store(StoreDepartmentRequest $request)
    {
        return $this->department->store($request);
    }

    public function show($department)
    {
        return $this->department->show($department);
    }

    public function update($department, UpdateDepartmentRequest $request)
    {
        return $this->department->update($department, $request);
    }

    public function destroy($department)
    {
        return $this->department->destroy($department);
    }
}