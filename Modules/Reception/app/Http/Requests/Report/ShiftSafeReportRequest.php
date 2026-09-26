<?php

namespace Modules\Reception\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ShiftSafeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id'   => ['nullable', 'integer', 'exists:shifts,id'],
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'date'       => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'shift_id.exists'           => 'رقم الشفت المحدد غير موجود.',
            'user_id.exists'            => 'المستخدم / الموظف المحدد غير موجود.',
            'date.date'                 => 'تاريخ التقرير يجب أن يكون تاريخاً صالحاً.',
            'start_date.date'           => 'تاريخ البداية غير صحيح.',
            'end_date.date'             => 'تاريخ النهاية غير صحيح.',
            'end_date.after_or_equal'   => 'تاريخ النهاية يجب أن يكون مساوياً أو بعد تاريخ البداية.',
        ];
    }
}
