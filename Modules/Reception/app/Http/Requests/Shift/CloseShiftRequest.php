<?php

namespace Modules\Reception\Http\Requests\Shift;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isReceptionist = auth()->user() && auth()->user()->type === UserType::RECEPTIONIST;

        return [
            'final_balance' => [$isReceptionist ? 'required' : 'nullable', 'numeric', 'min:0'],
        ];
    }
}
