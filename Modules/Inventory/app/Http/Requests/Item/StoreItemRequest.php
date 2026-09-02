<?php

namespace Modules\Inventory\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Inventory\Enums\ItemTypeEnum;
use Modules\Inventory\Enums\ItemUnitEnum;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // إذا كان النوع مستهلك (جلسات)، نجبر السعر على أن يكون 0 تلقائياً
        if ($this->type === ItemTypeEnum::CONSUMABLE->value) {
            $this->merge([
                'selling_price' => 0,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:items,name'],
            'unit' => ['required', new Enum(ItemUnitEnum::class)],
            'type' => ['required', new Enum(ItemTypeEnum::class)], // إضافة التحقق من النوع

            // السعر مطلوب فقط لو كان النوع منتج صيدلية (retailable)
            'selling_price' => [
                'required_if:type,' . ItemTypeEnum::RETAILABLE->value,
                'numeric',
                'min:0'
            ],

            'current_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'اسم الصنف مطلوب.',
            'name.unique'            => 'اسم الصنف موجود مسبقاً.',
            'unit.required'          => 'وحدة القياس مطلوبة.',
            'type.required'          => 'نوع الصنف مطلوب.',
            'selling_price.required_if' => 'سعر البيع مطلوب للمنتجات الصيدلانية.',
            'selling_price.numeric'     => 'سعر البيع يجب أن يكون رقماً.',
            'selling_price.min'         => 'سعر البيع يجب أن يكون صفر أو أكبر.',
            'current_stock.integer'     => 'الرصيد يجب أن يكون عدداً صحيحاً.',
            'current_stock.min'         => 'الرصيد يجب أن يكون صفراً أو أكبر.',
        ];
    }
}
