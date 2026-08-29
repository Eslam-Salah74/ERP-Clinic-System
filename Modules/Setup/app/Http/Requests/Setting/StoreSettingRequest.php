<?php

namespace Modules\Setup\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'unique:settings,key'],
            'value' => ['nullable', 'string'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:text,file,boolean,number'], // تحديد أنواع الإعدادات
        ];
    }
}
