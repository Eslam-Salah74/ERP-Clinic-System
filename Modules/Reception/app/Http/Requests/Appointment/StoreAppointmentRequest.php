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
            'patient_id'       => ['required', 'exists:patients,id'],
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', UserType::DOCTOR->value);
                }),
            ],
            'nurse_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', UserType::NURSE->value);
                }),
            ],
            'service_id'       => ['required', 'exists:services,id'],
            'appointment_date' => ['required', 'date'],
            'visit_type'       => ['nullable', new Enum(VisitTypeEnum::class)],
            'status'           => ['nullable', new Enum(AppointmentStatusEnum::class)],
            'notes'            => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required'       => 'بيانات المريض مطلوبة.',
            'patient_id.exists'         => 'المريض المحدد غير موجود.',
            'doctor_id.required'        => 'الطبيب مطلوب.',
            'doctor_id.exists'          => 'الطبيب المحدد غير موجود أو ليس طبيباً.',
            'nurse_id.exists'           => 'الممرض المحدد غير موجود أو ليس ممرضاً.',
            'service_id.required'       => 'الخدمة مطلوبة.',
            'service_id.exists'         => 'الخدمة المحددة غير موجودة.',
            'appointment_date.required' => 'تاريخ الموعد مطلوب.',
            'appointment_date.date'     => 'تاريخ الموعد غير صحيح.',
        ];
    }
}
