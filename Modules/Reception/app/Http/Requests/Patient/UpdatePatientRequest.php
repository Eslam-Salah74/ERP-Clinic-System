<?php

namespace Modules\Reception\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50', 'unique:patients,phone,' . $patientId],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_staff' => ['nullable', 'boolean'],
        ];
    }
}
