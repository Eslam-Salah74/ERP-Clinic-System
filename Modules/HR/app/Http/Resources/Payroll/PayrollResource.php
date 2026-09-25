<?php

namespace Modules\HR\Http\Resources\Payroll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'type' => $this->user->type,
                    'basic_salary' => (float) ($this->user->basic_salary ?? 0),
                    'achieved_target' => (bool) $this->user->achieved_target,
                ];
            }),
            'contract_id' => $this->contract_id,
            'month' => $this->month,
            'start_date' => $this->start_date ? \Carbon\Carbon::parse($this->start_date)->format('Y-m-d') : null,
            'end_date' => $this->end_date ? \Carbon\Carbon::parse($this->end_date)->format('Y-m-d') : null,
            'period_days' => ($this->start_date && $this->end_date)
                ? (int) \Carbon\Carbon::parse($this->start_date)->diffInDays(\Carbon\Carbon::parse($this->end_date)) + 1
                : null,

            // الراتب الأساسي وساعات العمل
            'basic_salary' => (float) $this->basic_salary,
            'shifts_count' => (int) $this->shifts_count,
            'total_working_hours' => (float) $this->total_working_hours,
            'hourly_pay' => (float) $this->hourly_pay,

            // الإضافي والتأخيرات وبدلات الإجازة
            'overtime_hours' => (float) $this->overtime_hours,
            'overtime_amount' => (float) $this->overtime_amount,
            'late_minutes' => (int) $this->late_minutes,
            'late_deduction_amount' => (float) $this->late_deduction_amount,
            'holiday_days' => (int) $this->holiday_days,
            'holiday_allowance_amount' => (float) $this->holiday_allowance_amount,

            // عمولات الخدمات للدكاترة
            'services_count' => (int) $this->services_count,
            'service_commissions_amount' => (float) $this->service_commissions_amount,

            // عمولات التمريض
            'device_sessions_count' => (int) $this->device_sessions_count,
            'device_commissions_amount' => (float) $this->device_commissions_amount,
            'product_sales_total' => (float) $this->product_sales_total,
            'product_commissions_amount' => (float) $this->product_commissions_amount,

            // عمولات الاستقبال
            'department_commissions_amount' => (float) $this->department_commissions_amount,

            // التارجت والبونص
            'target_achieved' => (bool) $this->target_achieved,
            'target_bonus_amount' => (float) $this->target_bonus_amount,

            // بدلات واستقطاعات
            'other_allowances' => (float) $this->other_allowances,
            'deductions' => (float) $this->deductions,

            // الإجمالي والصافي
            'gross_salary' => (float) $this->gross_salary,
            'net_salary' => (float) $this->net_salary,

            // الحالة والاعتماد
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn() => $this->approver?->name),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'paid_by' => $this->paid_by,
            'payer' => $this->whenLoaded('payer', fn() => $this->payer?->name),
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,

            // تفاصيل البنود
            'items' => PayrollItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
