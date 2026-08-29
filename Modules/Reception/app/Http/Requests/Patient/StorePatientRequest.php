<?php

namespace Modules\Reception\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:patients,phone'], // رقم فريد لعدم التكرار
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_staff' => ['nullable', 'boolean'],
        ];
    }
}
