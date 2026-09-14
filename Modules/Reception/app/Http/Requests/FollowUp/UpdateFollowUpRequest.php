<?php

namespace Modules\Reception\Http\Requests\FollowUp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reception\Enums\FollowUpStatusEnum;

class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'required', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'follow_up_date' => ['sometimes', 'required', 'date'],
            'status' => ['nullable', Rule::enum(FollowUpStatusEnum::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
