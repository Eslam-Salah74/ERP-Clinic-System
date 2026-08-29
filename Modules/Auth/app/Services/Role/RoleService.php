<?php

namespace Modules\Auth\Services\Role;

use Spatie\Permission\Models\Role;
use App\Support\API;
use Modules\Auth\Filters\Role\RoleFilter;
use Modules\Auth\Http\Resources\Role\RoleResource;

class RoleService
{
    public function index($request, RoleFilter $filter)
    {
        // استخدام الكويري بيلدر مباشرة لأن موديل Spatie لا يستخدم ترايت الفلتر الخاص بنا افتراضياً
        $query = Role::query()->with('permissions');

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $data = $query->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(RoleResource::collection($data))->build();
    }

    public function store($request)
    {
        $validatedData = $request->validated();

        $role = Role::create([
            'name' => $validatedData['name'],
            'guard_name' => 'api'
        ]);

        if (isset($validatedData['permissions'])) {
            $role->syncPermissions($validatedData['permissions']);
        }

        return API::newInstance()->isCreated('Role created successfully')->setData(new RoleResource($role->load('permissions')))->build();
    }

    public function show($id)
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return API::newInstance()->isError('Role not found')->build();
        }

        return API::newInstance()->isOk('Data retrieved successfully')->setData(new RoleResource($role))->build();
    }

    public function update($id, $request)
    {
        $role = Role::findOrFail($id);

        // منع التعديل على السوبر أدمن كنوع من الحماية
        if ($role->name === 'Super Admin' && $request->name !== 'Super Admin') {
            return API::newInstance()->isError('You cannot rename the Super Admin role.')->setStatus(403)->build();
        }

        $validatedData = $request->validated();

        if (isset($validatedData['name'])) {
            $role->update(['name' => $validatedData['name']]);
        }

        if (isset($validatedData['permissions'])) {
            $role->syncPermissions($validatedData['permissions']);
        }

        return API::newInstance()->isOk('Role updated successfully')->setData(new RoleResource($role->load('permissions')))->build();
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'Super Admin') {
            return API::newInstance()->isError('Super Admin role cannot be deleted.')->setStatus(403)->build();
        }

        $role->delete();
        return API::newInstance()->isOk('Role deleted successfully')->build();
    }
}
