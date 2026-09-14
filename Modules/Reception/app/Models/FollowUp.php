<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Reception\Enums\FollowUpStatusEnum;
use Modules\Reception\Filters\FollowUp\FollowUpFilter;

class FollowUp extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'follow_ups';
    protected $guarded = ['id'];

    protected $casts = [
        'follow_up_date' => 'datetime',
        'status' => FollowUpStatusEnum::class,
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter($query, FollowUpFilter $filter)
    {
        return $filter->apply($query);
    }
}
