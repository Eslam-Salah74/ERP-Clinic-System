<?php

namespace Modules\Auth\Services\Staff;

use App\Models\User;
use App\Support\API;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Filters\Staff\StaffFilter;
use Modules\Auth\Http\Resources\Staff\StaffResource;
use Spatie\Permission\Models\Role;

class StaffService
{
    public function index($request, StaffFilter $filter)
    {
        $perPage = $request->get('per_page', 10);
        $query = User::filter($filter)->with(['role.permissions', 'permissions'])->latest();

        $data = ($request->boolean('all') || $request->get('paginate') === 'false' || (string) $perPage === '-1')
            ? $query->get()
            : $query->paginate((int) $perPage);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(StaffResource::collection($data))->build();
    }

    public function store($request)
    {
        $validatedData = $request->validated();

        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        $data = User::create($validatedData);

        if (!empty($validatedData['role_id'])) {
            $role = Role::where('id', $validatedData['role_id'])
                ->where('guard_name', 'api')
                ->first();
            if ($role) {
                $data->syncRoles([$role->name]);
            }
        }

        // تم التعديل هنا ليعتمد على العلاقة المفردة role.permissions بدلاً من roles
        return API::newInstance()->isCreated('Created successfully')->setData(new StaffResource($data->load('role.permissions')))->build();
    }

    public function show($id)
    {
        $record = User::with(['role.permissions', 'permissions'])->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new StaffResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = User::findOrFail($id);
        $validatedData = $request->validated();

        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        $record->update($validatedData);

        if (!empty($validatedData['role_id'])) {
            $role = Role::where('id', $validatedData['role_id'])
                ->where('guard_name', 'api')
                ->first();
            if ($role) {
                $record->syncRoles([$role->name]);
            }
        }

        // تم التعديل هنا أيضاً ليعتمد على العلاقة المفردة role.permissions
        return API::newInstance()->isOk('Updated successfully')->setData(new StaffResource($record->load('role.permissions')))->build();
    }

    public function destroy($id)
    {
        $record = User::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
