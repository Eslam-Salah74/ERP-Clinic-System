<?php

namespace Modules\Reception\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],  // إحداثيات الـ GPS اللي باعتها الموظف
            'longitude' => ['nullable', 'numeric'], // خط الطول
        ];
    }
}
