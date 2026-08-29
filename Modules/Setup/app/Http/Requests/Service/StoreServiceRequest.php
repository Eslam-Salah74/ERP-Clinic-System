<?php

namespace Modules\Setup\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Setup\Enums\ServiceTypeEnum;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:services,name'],
            'department_id' => ['required', 'exists:departments,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            // نوع الخدمة (consultation, session, device)
            'type' => ['required', new Enum(ServiceTypeEnum::class)],

            // المنتجات المستهلكة (ممنوعة تماماً لو الكشف، ومسموحة للـ session والـ device)
            'items' => [
                'prohibited_if:type,' . ServiceTypeEnum::CONSULTATION->value,
                'nullable',
                'array'
            ],
            'items.*.item_id' => ['required_with:items', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.1'],
        ];
    }
}
