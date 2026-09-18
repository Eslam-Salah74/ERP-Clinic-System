<?php

namespace Modules\HR\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\ContractTypeEnum;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\HR\Enums\TargetTypeEnum;

class StoreStaffContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['required', Rule::enum(ContractTypeEnum::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],

            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'working_hours_per_day' => ['nullable', 'numeric', 'min:0'],
            'overtime_hour_rate' => ['nullable', 'numeric', 'min:0'],
            'late_deduction_rate_per_hour' => ['nullable', 'numeric', 'min:0'],
            'holiday_day_rate' => ['nullable', 'numeric', 'min:0'],

            'applies_department_commission' => ['nullable', 'boolean'],
            'department_commissions' => ['nullable', 'array'],
            'department_commissions.*.department_id' => ['required_with:department_commissions', 'exists:departments,id'],
            'department_commissions.*.percentage' => ['required_with:department_commissions', 'numeric', 'min:0'],

            'device_session_commission' => ['nullable', 'numeric', 'min:0'],
            'medication_sales_percentage' => ['nullable', 'numeric', 'min:0'],

            'default_service_commission_type' => ['nullable', Rule::enum(CommissionTypeEnum::class)],
            'default_service_commission_value' => ['nullable', 'numeric', 'min:0'],
            'laser_service_commission_percentage' => ['nullable', 'numeric', 'min:0'],
            'other_service_commission_percentage' => ['nullable', 'numeric', 'min:0'],

            'has_target' => ['nullable', 'boolean'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'target_type' => ['nullable', Rule::enum(TargetTypeEnum::class)],
            'target_achieved_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'target_achieved_laser_percentage' => ['nullable', 'numeric', 'min:0'],
            'target_achieved_other_percentage' => ['nullable', 'numeric', 'min:0'],
            'target_bonus' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string'],

            'service_commissions' => ['nullable', 'array'],
            'service_commissions.*.service_id' => ['required_with:service_commissions', 'exists:services,id'],
            'service_commissions.*.commission_type' => ['required_with:service_commissions', Rule::enum(CommissionTypeEnum::class)],
            'service_commissions.*.commission_value' => ['required_with:service_commissions', 'numeric', 'min:0'],
        ];
    }
}
