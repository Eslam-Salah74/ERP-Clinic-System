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
}
