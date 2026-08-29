<?php

namespace Modules\Reception\Http\Requests\Invoice;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Modules\Reception\Enums\InvoiceTypeEnum;
use Modules\Reception\Enums\PaymentMethodEnum;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'required', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],

            'type' => ['sometimes', 'required', new Enum(InvoiceTypeEnum::class)],
            'payment_method' => ['sometimes', 'required', new Enum(PaymentMethodEnum::class)],

            'doctor_id' => [
                'sometimes',
                'required_if:type,consultation,session',
                'nullable',
                Rule::exists('users', 'id')->where('type', UserType::DOCTOR->value)
            ],

            'nurse_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('type', UserType::NURSE->value)
            ],

            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.item_type' => ['required_with:items', 'in:service,product'],

            'items.*.service_id' => ['required_if:items.*.item_type,service', 'nullable', 'exists:services,id'],
            'items.*.product_id' => ['required_if:items.*.item_type,product', 'nullable', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],

            // تم حذف item_name و unit_price
        ];
    }
}
