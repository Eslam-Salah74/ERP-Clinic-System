<?php

namespace Modules\Reception\Services\Appointment;

use App\Support\API;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\VisitTypeEnum;
use Modules\Reception\Enums\ShiftStatusEnum; // أضفنا استدعاء الشفت إينوم
use Modules\Reception\Filters\Appointment\AppointmentFilter;
use Modules\Reception\Http\Resources\Appointment\AppointmentResource;
use Modules\Reception\Models\Appointment;
use Modules\Reception\Models\Shift; // أضفنا موديل الشفت
use Modules\Setup\Models\Service;
use Modules\Setup\Models\Setting;

class AppointmentService
{
    public function index($request, AppointmentFilter $filter)
    {
        $perPage = (int) $request->get('per_page', 15);
        $sortOrder = strtolower($request->get('sort_order', 'desc'));

        $query = Appointment::with(['patient', 'doctor', 'service.items', 'creator', 'shift'])
            ->filter($filter);

        if ($sortOrder === 'asc') {
            $query->oldest('appointment_date');
        } else {
            $query->latest('appointment_date');
        }

        $data = $query->paginate($perPage);

        return API::newInstance()->isOk('Data retrieved successfully')->setData(AppointmentResource::collection($data))->build();
    }

    public function store($request)
    {
        $validated = $request->validated();
        $userId = Auth::id();
        $validated['created_by'] = $userId;

        // 1. جلب الشفت النشط والمفتوح للمستخدم الحالي وربطه بالحجز
        $activeShift = Shift::where('user_id', $userId)
            ->where('status', ShiftStatusEnum::OPEN->value)
            ->first();

        if ($activeShift) {
            $validated['shift_id'] = $activeShift->id;
        }

        // 2. تعيين حالة افتراضية للحجز (pending) لو لم يتم إرسالها لتجنب ظهور null
        if (!isset($validated['status'])) {
            $validated['status'] = AppointmentStatusEnum::PENDING->value;
        }

        // 3. التحديد التلقائي لنوع الزيارة (كشف جديد أم متابعة)
        if (!isset($validated['visit_type'])) {
            $maxFollowUpDays = (int) (Setting::where('key', 'follow_up_max_days')->value('value') ?? 30);

            $lastAppointment = Appointment::where('patient_id', $validated['patient_id'])
                ->where('doctor_id', $validated['doctor_id'])
                ->where('status', AppointmentStatusEnum::COMPLETED->value)
                ->latest('appointment_date')
                ->first();

            if ($lastAppointment) {
                $daysDifference = Carbon::parse($lastAppointment->appointment_date)->diffInDays(Carbon::now());

                if ($daysDifference <= $maxFollowUpDays) {
                    $validated['visit_type'] = VisitTypeEnum::FOLLOW_UP->value;
                } else {
                    $validated['visit_type'] = VisitTypeEnum::CONSULTATION->value;
                }
            } else {
                $validated['visit_type'] = VisitTypeEnum::CONSULTATION->value;
            }
        }

        // 4. معالجة الأصناف المخزنية المرتبطة بالخدمة (service_items_ids)
        $customItemsIds = $validated['service_items_ids'] ?? $validated['serviceitemsids'] ?? null;

        if ($customItemsIds !== null) {
            $validated['service_items_ids'] = array_values(array_unique(array_map('intval', (array) $customItemsIds)));
        } elseif (!empty($validated['service_id'])) {
            $service = Service::with('items')->find($validated['service_id']);
            $validated['service_items_ids'] = $service
                ? $service->items->pluck('id')->values()->toArray()
                : [];
        } else {
            $validated['service_items_ids'] = [];
        }
        unset($validated['serviceitemsids']);

        $appointment = Appointment::create($validated);

        return API::newInstance()
            ->isCreated('Appointment created successfully')
            ->setData(new AppointmentResource($appointment->load(['patient', 'doctor', 'service.items', 'creator', 'shift'])))
            ->build();
    }

    public function show($id)
    {
        $record = Appointment::with(['patient', 'doctor', 'service.items', 'creator', 'shift'])->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new AppointmentResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = Appointment::findOrFail($id);
        $validated = $request->validated();

        $customItemsIds = $validated['service_items_ids'] ?? $validated['serviceitemsids'] ?? null;

        if ($customItemsIds !== null) {
            $validated['service_items_ids'] = array_values(array_unique(array_map('intval', (array) $customItemsIds)));
        } elseif (isset($validated['service_id']) && $validated['service_id'] != $record->service_id) {
            $service = Service::with('items')->find($validated['service_id']);
            $validated['service_items_ids'] = $service
                ? $service->items->pluck('id')->values()->toArray()
                : [];
        }
        unset($validated['serviceitemsids']);

        $record->update($validated);

        return API::newInstance()
            ->isOk('Updated successfully')
            ->setData(new AppointmentResource($record->load(['patient', 'doctor', 'service.items', 'creator', 'shift'])))
            ->build();
    }

    public function destroy($id)
    {
        $record = Appointment::findOrFail($id);
        $record->delete();

        return API::newInstance()->isOk('Deleted successfully')->build();
    }

    public function changeStatus($id, $request)
    {
        $appointment = Appointment::findOrFail($id);

        $appointment->update([
            'status' => $request->input('status')
        ]);

        return API::newInstance()
            ->isOk('تم تحديث حالة الحجز بنجاح')
            ->setData(new AppointmentResource($appointment->load(['patient', 'doctor', 'service.items', 'creator', 'shift'])))
            ->build();
    }
}
