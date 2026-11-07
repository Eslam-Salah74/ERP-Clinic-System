<?php

namespace Modules\HR\Http\Requests\CommissionTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\CommissionTypeEnum;

class UpdateCommissionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],

            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['required_with:items', 'exists:services,id'],
            'items.*.doctor_service_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_laser' => ['nullable', 'boolean'],
            'items.*.commission_type' => ['required_with:items', Rule::enum(CommissionTypeEnum::class)],
            'items.*.commission_value' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.target_commission_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

