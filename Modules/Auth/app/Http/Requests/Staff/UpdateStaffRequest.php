<?php

namespace Modules\Auth\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('staff') ?? $this->route('id');
        return [
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255', 'unique:users,email,' . $userId],
            'phone'           => ['sometimes', 'required', 'string', 'max:20', 'unique:users,phone,' . $userId],
            'password'        => ['nullable', 'sometimes', 'string', 'min:6'],
            'type'            => ['sometimes', 'string'],
            'role_id'         => ['required', 'exists:roles,id'],
            'basic_salary'    => ['required', 'numeric', 'min:0'],
            'allowances'      => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['required', 'boolean'],
            'achieved_target' => ['required', 'boolean'],
            'department_id'   => ['required', 'exists:departments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'اسم الموظف مطلوب.',
            'email.email'            => 'البريد الإلكتروني غير صحيح.',
            'email.unique'           => 'البريد الإلكتروني مسجل مسبقاً.',
            'phone.required'         => 'رقم الهاتف مطلوب.',
            'phone.unique'           => 'رقم الهاتف مسجل مسبقاً.',
            'password.min'           => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'role_id.required'       => 'الدور الوظيفي مطلوب.',
            'role_id.exists'         => 'الدور الوظيفي المحدد غير موجود.',
            'basic_salary.required'  => 'الراتب الأساسي مطلوب.',
            'basic_salary.numeric'   => 'الراتب الأساسي يجب أن يكون رقماً.',
            'basic_salary.min'       => 'الراتب الأساسي يجب أن يكون أكبر من أو يساوي صفر.',
            'is_active.required'     => 'حالة الموظف مطلوبة.',
            'is_active.boolean'      => 'حالة الموظف يجب أن تكون صح أو خطأ.',
            'achieved_target.boolean'  => 'حقل تحقيق الهدف يجب أن يكون صح أو خطأ.',
            'department_id.required' => 'القسم مطلوب.',
            'department_id.exists'   => 'القسم المحدد غير موجود.',
        ];
    }
}

