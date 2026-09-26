<?php

namespace Modules\Reception\Http\Requests\PatientPackage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\PaymentMethodEnum;

class PayPackageDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ المراد سداده مطلوب.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من الصفر.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
        ];
    }
}
