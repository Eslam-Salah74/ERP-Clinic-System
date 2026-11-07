<?php

namespace Modules\HR\Http\Requests\CommissionTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\CommissionTypeEnum;

class StoreCommissionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'exists:services,id'],
            'items.*.doctor_service_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_laser' => ['nullable', 'boolean'],
            'items.*.commission_type' => ['required', Rule::enum(CommissionTypeEnum::class)],
            'items.*.commission_value' => ['required', 'numeric', 'min:0'],
            'items.*.target_commission_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

