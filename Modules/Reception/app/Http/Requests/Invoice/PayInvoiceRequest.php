<?php

namespace Modules\Reception\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\PaymentMethodEnum;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ السداد مطلوب.',
            'amount.numeric' => 'مبلغ السداد يجب أن يكون رقماً.',
            'amount.gt' => 'مبلغ السداد يجب أن يكون أكبر من الصفر.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
        ];
    }
}
