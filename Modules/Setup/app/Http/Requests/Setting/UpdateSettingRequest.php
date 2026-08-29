<?php

namespace Modules\Setup\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settingId = $this->route('setting') ?? $this->route('id');

        return [
            'key' => ['sometimes', 'required', 'string', 'max:255', 'unique:settings,key,' . $settingId],
            'value' => ['nullable', 'string'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:text,file,boolean,number'],
        ];
    }
}
