<?php

namespace Modules\Auth\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'اسم الدور مطلوب.',
            'name.unique'            => 'اسم الدور موجود مسبقاً.',
            'name.max'               => 'اسم الدور يجب ألا يتجاوز 255 حرفاً.',
            'permissions.array'      => 'الصلاحيات يجب أن تكون قائمة.',
            'permissions.*.exists'   => 'إحدى الصلاحيات المحددة غير موجودة.',
        ];
    }
}
