<?php

namespace Modules\Auth\Http\Controllers\Api\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Auth\Services\Role\RoleService;
use Modules\Auth\Filters\Role\RoleFilter;
use Modules\Auth\Http\Requests\Role\StoreRoleRequest;
use Modules\Auth\Http\Requests\Role\UpdateRoleRequest;

class RoleController extends Controller implements HasMiddleware
{
    protected $role;

    public function __construct(RoleService $role)
    {
        $this->role = $role;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:read roles', only: ['index']),
            new Middleware('permission:show roles', only: ['show']),
            new Middleware('permission:create roles', only: ['store']),
            new Middleware('permission:update roles', only: ['update']),
            new Middleware('permission:delete roles', only: ['destroy']),
        ];
    }

    public function index(Request $request, RoleFilter $filter)
    {
        return $this->role->index($request, $filter);
    }

    public function store(StoreRoleRequest $request)
    {
        return $this->role->store($request);
    }

    public function show($id)
    {
        return $this->role->show($id);
    }

    public function update($id, UpdateRoleRequest $request)
    {
        return $this->role->update($id, $request);
    }

    public function destroy($id)
    {
        return $this->role->destroy($id);
    }
}
