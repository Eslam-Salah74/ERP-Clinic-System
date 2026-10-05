<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Enums\ContractTypeEnum;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\HR\Enums\TargetTypeEnum;

class StaffContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_contracts';
    protected $guarded = ['id'];

    protected $casts = [
        'contract_type' => ContractTypeEnum::class,
        'default_service_commission_type' => CommissionTypeEnum::class,
        'medication_commission_type' => CommissionTypeEnum::class,
        'target_type' => TargetTypeEnum::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'applies_department_commission' => 'boolean',
        'has_target' => 'boolean',
        'department_commissions' => 'array',
        'hourly_rate' => 'decimal:2',
        'working_hours_per_day' => 'decimal:2',
        'overtime_hour_rate' => 'decimal:2',
        'late_deduction_rate_per_hour' => 'decimal:2',
        'holiday_day_rate' => 'decimal:2',
        'device_session_commission' => 'decimal:2',
        'medication_commission_value' => 'decimal:2',
        'medication_sales_percentage' => 'decimal:2',
        'default_service_commission_value' => 'decimal:2',
        'laser_service_commission_percentage' => 'decimal:2',
        'other_service_commission_percentage' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'target_achieved_hourly_rate' => 'decimal:2',
        'target_achieved_laser_percentage' => 'decimal:2',
        'target_achieved_other_percentage' => 'decimal:2',
        'target_bonus' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (StaffContract $contract) {
            $type = $contract->medication_commission_type instanceof \BackedEnum
                ? $contract->medication_commission_type->value
                : ($contract->medication_commission_type ?? CommissionTypeEnum::PERCENTAGE->value);

            if ($type === CommissionTypeEnum::FIXED->value) {
                $contract->medication_commission_type = CommissionTypeEnum::FIXED;
                $contract->medication_commission_value = (float) ($contract->medication_commission_value ?? 0);
                $contract->medication_sales_percentage = 0;
            } else {
                $val = (float) ((float) $contract->medication_commission_value > 0
                    ? $contract->medication_commission_value
                    : ($contract->medication_sales_percentage ?? 0));

                $contract->medication_commission_type = CommissionTypeEnum::PERCENTAGE;
                $contract->medication_commission_value = $val;
                $contract->medication_sales_percentage = $val;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function serviceCommissions()
    {
        return $this->hasMany(ContractServiceCommission::class, 'contract_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'contract_id');
    }

    public function scopeFilter($query, \Modules\HR\Filters\Contract\StaffContractFilter $filter)
    {
        return $filter->apply($query);
    }
}
