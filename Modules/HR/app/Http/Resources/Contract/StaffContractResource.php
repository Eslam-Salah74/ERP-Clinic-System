<?php

namespace Modules\HR\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffContractResource extends JsonResource
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
            'title' => $this->title,
            'contract_type' => $this->contract_type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => (bool) $this->is_active,

            // ساعات العمل والمعدلات
            'hourly_rate' => (float) $this->hourly_rate,
            'working_hours_per_day' => (float) $this->working_hours_per_day,
            'overtime_hour_rate' => (float) $this->overtime_hour_rate,
            'late_deduction_rate_per_hour' => (float) $this->late_deduction_rate_per_hour,
            'holiday_day_rate' => (float) $this->holiday_day_rate,

            // عمولات الاستقبال والإدارة
            'applies_department_commission' => (bool) $this->applies_department_commission,
            'department_commissions' => $this->department_commissions ?? [],

            // عمولات التمريض
            'device_session_commission' => (float) $this->device_session_commission,
            'medication_sales_percentage' => (float) $this->medication_sales_percentage,

            // عمولات الدكاترة
            'default_service_commission_type' => $this->default_service_commission_type,
            'default_service_commission_value' => (float) $this->default_service_commission_value,
            'laser_service_commission_percentage' => (float) $this->laser_service_commission_percentage,
            'other_service_commission_percentage' => (float) $this->other_service_commission_percentage,

            // نظام التارجت
            'has_target' => (bool) $this->has_target,
            'target_amount' => (float) $this->target_amount,
            'target_type' => $this->target_type,
            'target_achieved_hourly_rate' => (float) $this->target_achieved_hourly_rate,
            'target_achieved_laser_percentage' => (float) $this->target_achieved_laser_percentage,
            'target_achieved_other_percentage' => (float) $this->target_achieved_other_percentage,
            'target_bonus' => (float) $this->target_bonus,

            // العمولات المخصصة لكل خدمة
            'service_commissions' => ContractServiceCommissionResource::collection($this->whenLoaded('serviceCommissions')),

            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
