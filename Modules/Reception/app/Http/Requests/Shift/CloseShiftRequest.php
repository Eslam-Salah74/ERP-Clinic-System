<?php

namespace Modules\Reception\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'final_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}
