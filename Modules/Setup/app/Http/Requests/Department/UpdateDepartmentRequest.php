<?php

namespace Modules\Setup\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('department') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:departments,name,' . $departmentId],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
