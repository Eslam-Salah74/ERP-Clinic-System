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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $userId],
            'phone' => ['sometimes', 'required', 'string', 'max:20', 'unique:users,phone,' . $userId],
            'password' => ['required', 'string', 'min:6'],
            'type' => ['sometimes', 'string'],
            'role_id' => ['required', 'exists:roles,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'achieved_target' => ['required', 'boolean'],
            'department_id' => ['required', 'exists:departments,id'],
        ];
    }
}
