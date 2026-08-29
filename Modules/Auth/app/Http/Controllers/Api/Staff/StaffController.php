<?php

namespace Modules\Auth\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Auth\Services\Staff\StaffService;
use Modules\Auth\Filters\Staff\StaffFilter;
use Modules\Auth\Http\Requests\Staff\StoreStaffRequest;
use Modules\Auth\Http\Requests\Staff\UpdateStaffRequest;

class StaffController extends Controller implements HasMiddleware
{
    protected $staff;

    public function __construct(StaffService $staff)
    {
        $this->staff = $staff;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read staff', only: ['index']),
            new Middleware('permission:show staff', only: ['show']),
            new Middleware('permission:create staff', only: ['store']),
            new Middleware('permission:update staff', only: ['update']),
            new Middleware('permission:delete staff', only: ['destroy']),
        ];
    }

    public function index(Request $request, StaffFilter $filter)
    {
        return $this->staff->index($request, $filter);
    }

    public function store(StoreStaffRequest $request)
    {
        return $this->staff->store($request);
    }

    public function show($staff)
    {
        return $this->staff->show($staff);
    }

    public function update($staff, UpdateStaffRequest $request)
    {
        return $this->staff->update($staff, $request);
    }

    public function destroy($staff)
    {
        return $this->staff->destroy($staff);
    }
}