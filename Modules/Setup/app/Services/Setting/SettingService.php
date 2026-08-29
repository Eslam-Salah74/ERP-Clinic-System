<?php

namespace Modules\Setup\Services\Setting;

use Modules\Setup\Models\Setting;
use Modules\Setup\Filters\Setting\SettingFilter;
use Modules\Setup\Http\Resources\Setting\SettingResource;
use App\Support\API;

class SettingService
{
    public function index($request, SettingFilter $filter)
    {
        $data = Setting::filter($filter)->get(); // شيلنا الباجينيشن عشان دي إعدادات بنحتاجها كلها في الفرونت
        return API::newInstance()->isOk('Data retrieved successfully')->setData(SettingResource::collection($data))->build();
    }

    public function store($request)
    {
        $data = Setting::create($request->validated());
        return API::newInstance()->isCreated('Created successfully')->setData(new SettingResource($data))->build();
    }

    public function show($id)
    {
        $record = Setting::find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new SettingResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Setting::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new SettingResource($record))->build();
    }

    public function destroy($id)
    {
        $record = Setting::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
