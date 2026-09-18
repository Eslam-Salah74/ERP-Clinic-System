<?php

namespace Modules\HR\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
