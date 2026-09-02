<?php

namespace Modules\Setup\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255', 'unique:departments,name'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'اسم القسم مطلوب.',
            'name.unique'     => 'اسم القسم موجود مسبقاً.',
            'name.max'        => 'اسم القسم يجب ألا يتجاوز 255 حرفاً.',
            'is_active.required' => 'حالة القسم مطلوبة.',
            'is_active.boolean'  => 'حالة القسم يجب أن تكون صح أو خطأ.',
        ];
    }
}
