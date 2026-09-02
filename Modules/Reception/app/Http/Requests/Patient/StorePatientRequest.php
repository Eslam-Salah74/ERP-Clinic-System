<?php

namespace Modules\Reception\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:50', 'unique:patients,phone'],
            'gender'   => ['nullable', 'string', 'in:male,female'],
            'age'      => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_staff' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'اسم المريض مطلوب.',
            'phone.required' => 'رقم هاتف المريض مطلوب.',
            'phone.unique'   => 'رقم الهاتف مسجل مسبقاً لمريض آخر.',
            'gender.in'      => 'الجنس يجب أن يكون (ذكر أو أنثى).',
            'age.integer'    => 'العمر يجب أن يكون عدداً صحيحاً.',
            'age.min'        => 'العمر يجب أن يكون صفراً أو أكبر.',
            'age.max'        => 'العمر يجب ألا يتجاوز 120 سنة.',
        ];
    }
}
