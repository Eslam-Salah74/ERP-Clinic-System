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
        $data = User::filter($filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(StaffResource::collection($data))->build();
    }

    public function store($request)
    {
        $validatedData = $request->validated();

        // تشفير كلمة المرور قبل الحفظ
        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        $data = User::create($validatedData);

        // ✅ ربط المستخدم بالرول في Spatie (جدول model_has_roles)
        // role_id بيتحفظ في عمود users، لكن Spatie محتاج ربط منفصل عشان الصلاحيات تشتغل
        if (!empty($validatedData['role_id'])) {
            $role = Role::where('id', $validatedData['role_id'])
                ->where('guard_name', 'api')
                ->first();
            if ($role) {
                $data->syncRoles([$role->name]);
            }
        }

        return API::newInstance()->isCreated('Created successfully')->setData(new StaffResource($data->load('roles')))->build();
    }

    public function show($id)
    {
        $record = User::find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new StaffResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = User::findOrFail($id);
        $validatedData = $request->validated();

        // لو تم إرسال باسورد جديد يتم تشفيره، لو فارغ يتم حذفه من المصفوفة حتى لا يعدل القديم بـ null
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        $record->update($validatedData);

        // ✅ تحديث ربط المستخدم بالرول في Spatie لو تغير الـ role_id
        if (!empty($validatedData['role_id'])) {
            $role = Role::where('id', $validatedData['role_id'])
                ->where('guard_name', 'api')
                ->first();
            if ($role) {
                $record->syncRoles([$role->name]);
            }
        }

        return API::newInstance()->isOk('Updated successfully')->setData(new StaffResource($record->load('roles')))->build();
    }

    public function destroy($id)
    {
        $record = User::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
