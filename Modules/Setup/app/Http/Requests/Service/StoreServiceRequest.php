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
            'name'          => ['required', 'string', 'max:255', 'unique:services,name'],
            'department_id' => ['required', 'exists:departments,id'],
            'price'         => ['required', 'numeric', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
            'type'          => ['required', new Enum(ServiceTypeEnum::class)],
            'items' => [
                'prohibited_if:type,' . ServiceTypeEnum::CONSULTATION->value,
                'nullable',
                'array'
            ],
            'items.*.item_id'  => ['required_with:items', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.price'    => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'اسم الخدمة مطلوب.',
            'name.unique'            => 'اسم الخدمة موجود مسبقاً.',
            'department_id.required' => 'القسم مطلوب.',
            'department_id.exists'   => 'القسم المحدد غير موجود.',
            'price.required'         => 'سعر الخدمة مطلوب.',
            'price.numeric'          => 'سعر الخدمة يجب أن يكون رقماً.',
            'price.min'              => 'سعر الخدمة يجب أن يكون أكبر من أو يساوي صفر.',
            'type.required'          => 'نوع الخدمة مطلوب.',
            'type.enum'              => 'نوع الخدمة غير صحيح.',
            'items.prohibited_if'    => 'لا يمكن إضافة مواد مخزنية لخدمة الكشف (متاحة للجلسات والأجهزة فقط).',
            'items.array'            => 'الأصناف يجب أن تكون قائمة.',
            'items.*.item_id.required_with' => 'معرف الصنف مطلوب.',
            'items.*.item_id.exists'        => 'الصنف المحدد غير موجود.',
            'items.*.quantity.required_with' => 'كمية الصنف مطلوبة.',
            'items.*.quantity.gt'           => 'كمية الصنف يجب أن تكون أكبر من 0.',
            'items.*.price.numeric'         => 'سعر المستلزم يجب أن يكون رقماً.',
            'items.*.price.min'             => 'سعر المستلزم يجب أن يكون أكبر من أو يساوي صفر.',
        ];
    }
}
