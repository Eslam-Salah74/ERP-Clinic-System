<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Reception\Enums\PatientPackageStatusEnum;
use Modules\Reception\Filters\PatientPackage\PatientPackageFilter;
use Modules\Setup\Models\Package;

class PatientPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patient_packages';
    protected $guarded = ['id'];

    protected $fillable = [
        'patient_id',
        'package_id',
        'invoice_id',
        'total_price',
        'paid_amount',
        'remaining_amount',
        'status',
        'start_date',
        'expires_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => PatientPackageStatusEnum::class,
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'start_date' => 'date',
        'expires_at' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function balances()
    {
        return $this->hasMany(PatientPackageBalance::class, 'patient_package_id');
    }

    public function consumptions()
    {
        return $this->hasMany(PatientPackageConsumption::class, 'patient_package_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter($query, PatientPackageFilter $filter)
    {
        return $filter->apply($query);
    }
}
