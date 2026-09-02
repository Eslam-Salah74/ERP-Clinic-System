<?php

namespace Modules\Inventory\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Inventory\Enums\ItemTypeEnum;
use Modules\Inventory\Enums\ItemUnitEnum;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // نفس الحماية في حالة التعديل
        if ($this->has('type') && $this->type === ItemTypeEnum::CONSUMABLE->value) {
            $this->merge([
                'selling_price' => 0,
            ]);
        }
    }

    public function rules(): array
    {
        $itemId = $this->route('item') ?? $this->route('id');

        return [
            'name'          => ['sometimes', 'required', 'string', 'max:255', 'unique:items,name,' . $itemId],
            'unit'          => ['sometimes', 'required', new Enum(ItemUnitEnum::class)],
            'type'          => ['sometimes', 'required', new Enum(ItemTypeEnum::class)],
            'selling_price' => [
                'required_if:type,' . ItemTypeEnum::RETAILABLE->value,
                'numeric',
                'min:0'
            ],
            'current_stock' => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'               => 'اسم الصنف موجود مسبقاً.',
            'selling_price.required_if' => 'سعر البيع مطلوب للمنتجات الصيدلانية.',
            'selling_price.numeric'     => 'سعر البيع يجب أن يكون رقماً.',
            'selling_price.min'         => 'سعر البيع يجب أن يكون صفر أو أكبر.',
            'current_stock.integer'     => 'الرصيد يجب أن يكون عدداً صحيحاً.',
        ];
    }
}
