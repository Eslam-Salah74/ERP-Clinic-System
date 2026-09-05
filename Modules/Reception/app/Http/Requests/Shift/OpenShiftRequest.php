<?php

namespace Modules\Reception\Http\Requests\Shift;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isReceptionist = auth()->user() && auth()->user()->type === UserType::RECEPTIONIST;

        return [
            'initial_balance' => [$isReceptionist ? 'required' : 'nullable', 'numeric', 'min:0'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ];
    }
}
