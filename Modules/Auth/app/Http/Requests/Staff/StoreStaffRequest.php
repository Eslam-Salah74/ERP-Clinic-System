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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'type' => ['required', 'string'],
            'role_id' => ['required', 'exists:roles,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'achieved_target' => ['required', 'boolean'],
            'department_id' => ['required', 'exists:departments,id'],
        ];
    }
}
