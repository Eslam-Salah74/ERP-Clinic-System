<?php

namespace Modules\Reception\Http\Requests\Report;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class DateRangeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $preset = $this->input('period_preset');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        // إذا تم اختيار فلتر زمني سريع (Preset) أو لم يتم إرسال تواريخ نهائياً
        if ($preset || (empty($startDate) && empty($endDate))) {
            $preset = $preset ?: 'this_month';

            switch ($preset) {
                case 'today':
                    $startDate = Carbon::today()->toDateString();
                    $endDate   = Carbon::today()->toDateString();
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday()->toDateString();
                    $endDate   = Carbon::yesterday()->toDateString();
                    break;
                case 'this_week':
                    $startDate = Carbon::now()->startOfWeek()->toDateString();
                    $endDate   = Carbon::now()->endOfWeek()->toDateString();
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->startOfMonth()->toDateString();
                    $endDate   = Carbon::now()->subMonth()->endOfMonth()->toDateString();
                    break;
                case 'this_month':
                default:
                    $startDate = Carbon::now()->startOfMonth()->toDateString();
                    $endDate   = Carbon::now()->endOfMonth()->toDateString();
                    break;
            }
        } elseif (!empty($startDate) && empty($endDate)) {
            $endDate = $startDate;
        }

        // دعم device_id كمترادف لـ service_id لسهولة الاستخدام
        $serviceId = $this->input('service_id') ?? $this->input('device_id');

        $this->merge([
            'period_preset' => $preset ?? 'custom',
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'service_id'    => $serviceId,
            'device_id'     => $serviceId,
        ]);
    }

    public function rules(): array
    {
        return [
            'period_preset'  => ['nullable', 'string', 'in:today,yesterday,this_week,this_month,last_month,custom'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'department_id'  => ['nullable', 'integer', 'exists:departments,id'],
            'doctor_id'      => ['nullable', 'integer', 'exists:users,id'],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'device_id'      => ['nullable', 'integer', 'exists:services,id'],
            'shift_id'       => ['nullable', 'integer', 'exists:shifts,id'],
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
            'device_id.exists'          => 'الجهاز المحدد غير موجود.',
            'shift_id.exists'           => 'الشفت المحدد غير موجود.',
        ];
    }
}
