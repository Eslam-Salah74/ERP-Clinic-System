<?php

namespace Modules\Reception\Http\Requests\PatientPackage;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\PaymentMethodEnum;

class SubscribePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'package_id' => ['required', 'exists:packages,id'],
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'doctor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('type', UserType::DOCTOR->value)
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'بيانات المريض مطلوبة.',
            'package_id.required' => 'يجب اختيار الباقة / العرض.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
        ];
    }
}
