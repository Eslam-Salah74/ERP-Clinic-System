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
