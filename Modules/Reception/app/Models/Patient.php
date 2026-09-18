<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Reception\Filters\Patient\PatientFilter;


class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patients';
    protected $guarded = ['id'];
    protected $fillable = [
        'name',
        'phone',
        'gender',
        'age',
        'is_staff',
        'created_by',
    ];

    // علاقة موظف الاستقبال اللي سجله
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // جميع حجوزات المريض
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    // جميع فواتير المريض
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'patient_id');
    }

    // جميع متابعات المريض
    public function followUps()
    {
        return $this->hasMany(FollowUp::class, 'patient_id');
    }

    public function scopeFilter($query, PatientFilter $filter)
    {
        return $filter->apply($query);
    }
}
