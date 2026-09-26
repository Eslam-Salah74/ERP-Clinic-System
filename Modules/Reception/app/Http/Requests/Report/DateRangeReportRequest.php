<?php

namespace Modules\Reception\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class DateRangeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'department_id'  => ['nullable', 'integer', 'exists:departments,id'],
            'doctor_id'      => ['nullable', 'integer', 'exists:users,id'],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'payment_method' => ['nullable', 'string'],
            'category'       => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required'       => 'تاريخ البداية مطلوب لتوليد التقرير بدقة.',
            'start_date.date'           => 'تاريخ البداية غير صالح.',
            'end_date.required'         => 'تاريخ النهاية مطلوب لتوليد التقرير بدقة.',
            'end_date.date'             => 'تاريخ النهاية غير صالح.',
            'end_date.after_or_equal'   => 'تاريخ النهاية يجب أن يكون مساوياً أو بعد تاريخ البداية.',
            'department_id.exists'      => 'القسم المحدد غير موجود.',
            'doctor_id.exists'          => 'الطبيب المحدد غير موجود.',
            'service_id.exists'         => 'الخدمة / الجهاز المحدد غير موجود.',
        ];
    }
}
