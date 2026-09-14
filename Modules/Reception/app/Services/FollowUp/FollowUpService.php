<?php

namespace Modules\Reception\Services\FollowUp;

use App\Support\API;
use Illuminate\Support\Facades\Auth;
use Modules\Reception\Enums\FollowUpStatusEnum;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Filters\FollowUp\FollowUpFilter;
use Modules\Reception\Http\Resources\FollowUp\FollowUpResource;
use Modules\Reception\Models\FollowUp;
use Modules\Reception\Models\Shift;

class FollowUpService
{
    public function index($request, FollowUpFilter $filter)
    {
        $data = FollowUp::with(['patient', 'doctor', 'appointment', 'shift', 'creator'])
            ->filter($filter)
            ->latest('follow_up_date')
            ->paginate(10);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(FollowUpResource::collection($data))->build();
    }

    public function store($request)
    {
        $validated = $request->validated();
        $userId = Auth::id();
        $validated['created_by'] = $userId;

        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if ($activeShift && empty($validated['shift_id'])) {
            $validated['shift_id'] = $activeShift->id;
        }

        if (!isset($validated['status'])) {
            $validated['status'] = FollowUpStatusEnum::PENDING->value;
        }

        $data = FollowUp::create($validated);

        return API::newInstance()
            ->isCreated('Created successfully')
            ->setData(new FollowUpResource($data->load(['patient', 'doctor', 'appointment', 'shift', 'creator'])))
            ->build();
    }

    public function show($id)
    {
        $record = FollowUp::with(['patient', 'doctor', 'appointment', 'shift', 'creator'])->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new FollowUpResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = FollowUp::findOrFail($id);
        $record->update($request->validated());

        return API::newInstance()
            ->isOk('Updated successfully')
            ->setData(new FollowUpResource($record->load(['patient', 'doctor', 'appointment', 'shift', 'creator'])))
            ->build();
    }

    public function destroy($id)
    {
        $record = FollowUp::findOrFail($id);
        $record->delete();

        return API::newInstance()->isOk('Deleted successfully')->build();
    }

    public function changeStatus($id, $request)
    {
        $record = FollowUp::findOrFail($id);
        $record->update([
            'status' => $request->input('status')
        ]);

        return API::newInstance()
            ->isOk('تم تحديث حالة المتابعة بنجاح')
            ->setData(new FollowUpResource($record->load(['patient', 'doctor', 'appointment', 'shift', 'creator'])))
            ->build();
    }
}
