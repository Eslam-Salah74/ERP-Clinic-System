<?php

namespace Modules\Inventory\Http\Requests\PurchaseInvoice;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'           => ['required', 'exists:suppliers,id'],
            'notes'                 => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.item_id'       => ['required', 'exists:items,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.purchase_price'=> ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required'          => 'المورد مطلوب.',
            'supplier_id.exists'            => 'المورد المحدد غير موجود.',
            'items.required'                => 'يجب إضافة صنف واحد على الأقل.',
            'items.min'                     => 'يجب إضافة صنف واحد على الأقل.',
            'items.*.item_id.required'      => 'الصنف مطلوب.',
            'items.*.item_id.exists'        => 'الصنف المحدد غير موجود.',
            'items.*.quantity.required'     => 'كمية الصنف مطلوبة.',
            'items.*.quantity.min'          => 'الكمية يجب أن تكون 1 على الأقل.',
            'items.*.purchase_price.required'=> 'سعر الشراء مطلوب.',
            'items.*.purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقماً.',
            'items.*.purchase_price.min'    => 'سعر الشراء يجب أن يكون صفراً أو أكبر.',
        ];
    }
}
