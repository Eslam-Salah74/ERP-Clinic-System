<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\HR\Enums\CommissionTypeEnum;
use Modules\Setup\Models\Service;

class ContractServiceCommission extends Model
{
    use HasFactory;

    protected $table = 'contract_service_commissions';
    protected $guarded = ['id'];

    protected $fillable = [
        'contract_id',
        'service_id',
        'doctor_service_price',
        'is_laser',
        'commission_type',
        'commission_value',
        'target_commission_value',
    ];

    protected $casts = [
        'doctor_service_price' => 'decimal:2',
        'is_laser' => 'boolean',
        'commission_type' => CommissionTypeEnum::class,
        'commission_value' => 'decimal:2',
        'target_commission_value' => 'decimal:2',
    ];

    public function contract()
    {
        return $this->belongsTo(StaffContract::class, 'contract_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
