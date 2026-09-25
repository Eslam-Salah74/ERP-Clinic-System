<?php

namespace Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'month' => ['nullable', 'date_format:Y-m', 'required_without:start_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_without:month'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'required_with:start_date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'holiday_days' => ['nullable', 'integer', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
