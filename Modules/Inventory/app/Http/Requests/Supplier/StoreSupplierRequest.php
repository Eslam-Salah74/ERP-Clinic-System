<?php

namespace Modules\Inventory\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المورد مطلوب.',
            'name.max'      => 'اسم المورد يجب ألا يتجاوز 255 حرفاً.',
        ];
    }
}
