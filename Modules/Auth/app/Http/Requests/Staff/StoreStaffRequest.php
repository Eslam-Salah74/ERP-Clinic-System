<?php

namespace Modules\Auth\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone'           => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password'        => ['required', 'string', 'min:6'],
            'type'            => ['required', 'string'],
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
            'name.max'               => 'اسم الموظف يجب ألا يتجاوز 255 حرفاً.',
            'email.email'            => 'البريد الإلكتروني غير صحيح.',
            'email.unique'           => 'البريد الإلكتروني مسجل مسبقاً.',
            'phone.required'         => 'رقم الهاتف مطلوب.',
            'phone.unique'           => 'رقم الهاتف مسجل مسبقاً.',
            'password.required'      => 'كلمة المرور مطلوبة.',
            'password.min'           => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'type.required'          => 'نوع الموظف مطلوب.',
            'role_id.required'       => 'الدور الوظيفي مطلوب.',
            'role_id.exists'         => 'الدور الوظيفي المحدد غير موجود.',
            'basic_salary.required'  => 'الراتب الأساسي مطلوب.',
            'basic_salary.numeric'   => 'الراتب الأساسي يجب أن يكون رقماً.',
            'basic_salary.min'       => 'الراتب الأساسي يجب أن يكون أكبر من أو يساوي صفر.',
            'allowances.numeric'     => 'البدلات يجب أن تكون رقماً.',
            'is_active.required'     => 'حالة الموظف مطلوبة.',
            'is_active.boolean'      => 'حالة الموظف يجب أن تكون صح أو خطأ.',
            'achieved_target.required' => 'حقل تحقيق الهدف مطلوب.',
            'achieved_target.boolean'  => 'حقل تحقيق الهدف يجب أن يكون صح أو خطأ.',
            'department_id.required' => 'القسم مطلوب.',
            'department_id.exists'   => 'القسم المحدد غير موجود.',
        ];
    }
}

