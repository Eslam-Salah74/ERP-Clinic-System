<?php

namespace Modules\Setup\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Setup\Enums\ServiceTypeEnum;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // التقاط الـ ID الخاص بالخدمة الحالية لتجاهلها في فحص الـ Unique
        $serviceId = $this->route('service') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:services,name,' . $serviceId],
            'department_id' => ['sometimes', 'required', 'exists:departments,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            'type' => ['sometimes', 'required', new Enum(ServiceTypeEnum::class)],

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
