<?php

namespace Modules\Reception\Services\Patient;

use Modules\Reception\Models\Patient;
use Modules\Reception\Filters\Patient\PatientFilter;
use Modules\Reception\Http\Resources\Patient\PatientResource;
use App\Support\API;
use Illuminate\Support\Facades\Auth;

class PatientService
{
    public function index($request, PatientFilter $filter)
    {
        $data = Patient::with('creator')->filter($filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(PatientResource::collection($data))->build();
    }

    public function store($request)
    {
        $validated = $request->validated();
        // تسجيل الأيدي الخاص بالموظف الحالي المسجل دخول
        $validated['created_by'] = Auth::id();

        $data = Patient::create($validated);
        return API::newInstance()->isCreated('Created successfully')->setData(new PatientResource($data->load('creator')))->build();
    }

    public function show($id)
    {
        $record = Patient::with('creator')->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new PatientResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Patient::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new PatientResource($record->load('creator')))->build();
    }

    public function destroy($id)
    {
        $record = Patient::findOrFail($id);
        $record->delete();
        return API::newInstance()->isOk('Deleted successfully')->build();
    }
}
