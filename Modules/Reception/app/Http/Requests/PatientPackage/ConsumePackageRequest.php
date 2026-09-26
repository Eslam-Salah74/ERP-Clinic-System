<?php

namespace Modules\Reception\Http\Requests\PatientPackage;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsumePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_package_balance_id' => ['required', 'exists:patient_package_balances,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where('type', UserType::DOCTOR->value)
            ],
            'nurse_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('type', UserType::NURSE->value)
            ],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_package_balance_id.required' => 'يجب اختيار البند أو الجلسة المراد استهلاكها.',
            'quantity.required' => 'الكمية المراد استهلاكها مطلوبة.',
            'doctor_id.required' => 'يجب تحديد الطبيب المنفّذ للجلسة لاحتساب عمولته ودوره.',
        ];
    }
}
