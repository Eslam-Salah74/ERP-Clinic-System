<?php

namespace Modules\Reception\Http\Requests\Appointment;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\VisitTypeEnum;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],

            // التحقق من أن المستخدم طبيب معتمد باستخدام Rule::exists مع شرط type
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', UserType::DOCTOR->value);
                }),
            ],

            // التحقق من أن المستخدم ممرض معتمد (اختياري / nullable)
            'nurse_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', UserType::NURSE->value);
                }),
            ],

            'service_id' => ['required', 'exists:services,id'],
            'appointment_date' => ['required', 'date'],
            'visit_type' => ['nullable', new Enum(VisitTypeEnum::class)],
            'status' => ['nullable', new Enum(AppointmentStatusEnum::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
