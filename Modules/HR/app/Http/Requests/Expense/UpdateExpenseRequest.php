<?php

namespace Modules\HR\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\ExpenseCategoryEnum;
use Modules\Reception\Enums\PaymentMethodEnum;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::enum(ExpenseCategoryEnum::class)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethodEnum::class)],
            'expense_date' => ['sometimes', 'required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
