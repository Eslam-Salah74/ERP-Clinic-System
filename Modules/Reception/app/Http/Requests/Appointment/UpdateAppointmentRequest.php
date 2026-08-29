<?php

namespace Modules\Reception\Http\Requests\Appointment;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\VisitTypeEnum;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'required', 'exists:patients,id'],

            'doctor_id' => [
                'sometimes',
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', UserType::DOCTOR->value);
                }),
            ],

            'nurse_id' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', UserType::NURSE->value);
                }),
            ],

            'service_id' => ['sometimes', 'required', 'exists:services,id'],
            'appointment_date' => ['sometimes', 'required', 'date'],
            'visit_type' => ['nullable', new Enum(VisitTypeEnum::class)],
            'status' => ['nullable', new Enum(AppointmentStatusEnum::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
