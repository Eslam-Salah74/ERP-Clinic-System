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

    protected $casts = [
        'commission_type' => CommissionTypeEnum::class,
        'commission_value' => 'decimal:2',
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
