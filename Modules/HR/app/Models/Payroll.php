<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Enums\PayrollStatusEnum;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payrolls';
    protected $guarded = ['id'];

    protected $casts = [
        'status' => PayrollStatusEnum::class,
        'target_achieved' => 'boolean',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'basic_salary' => 'decimal:2',
        'total_working_hours' => 'decimal:2',
        'hourly_pay' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'late_deduction_amount' => 'decimal:2',
        'holiday_allowance_amount' => 'decimal:2',
        'service_commissions_amount' => 'decimal:2',
        'device_commissions_amount' => 'decimal:2',
        'product_sales_total' => 'decimal:2',
        'product_commissions_amount' => 'decimal:2',
        'department_commissions_amount' => 'decimal:2',
        'target_bonus_amount' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contract()
    {
        return $this->belongsTo(StaffContract::class, 'contract_id');
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class, 'payroll_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeFilter($query, \Modules\HR\Filters\Payroll\PayrollFilter $filter)
    {
        return $filter->apply($query);
    }
}
