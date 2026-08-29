<?php

namespace Modules\Reception\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\AppointmentStatusEnum;

class ChangeAppointmentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // التحقق من أن الحالة المرسلة موجودة فعلاً داخل الـ Enum
            'status' => ['required', new Enum(AppointmentStatusEnum::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'حالة الحجز مطلوبة.',
            'status.Illuminate\Validation\Rules\Enum' => 'حالة الحجز المحددة غير صالحة.',
        ];
    }
}
