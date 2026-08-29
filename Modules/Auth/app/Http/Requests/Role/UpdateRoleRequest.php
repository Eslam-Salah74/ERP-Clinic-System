<?php

namespace Modules\Auth\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:roles,name,' . $roleId],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
