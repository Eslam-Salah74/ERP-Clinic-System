<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Models\Item;
use Modules\Setup\Enums\PackageItemTypeEnum;
use Modules\Setup\Models\Service;

class PatientPackageBalance extends Model
{
    use HasFactory;

    protected $table = 'patient_package_balances';
    protected $guarded = ['id'];

    protected $fillable = [
        'patient_package_id',
        'item_type',
        'service_id',
        'product_id',
        'custom_name',
        'total_quantity',
        'consumed_quantity',
        'remaining_quantity',
        'unit',
    ];

    protected $casts = [
        'item_type' => PackageItemTypeEnum::class,
        'total_quantity' => 'decimal:2',
        'consumed_quantity' => 'decimal:2',
        'remaining_quantity' => 'decimal:2',
    ];

    public function patientPackage()
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function consumptions()
    {
        return $this->hasMany(PatientPackageConsumption::class, 'patient_package_balance_id');
    }
}
