<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Reception\Enums\ShiftStatusEnum;
use Modules\Reception\Filters\Shift\ShiftFilter;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shifts';
    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'status',
        'initial_balance',
        'final_balance',
        'start_time',
        'end_time',
        'opening_latitude',
        'opening_longitude',
        'is_late',
        'late_minutes',
        'overtime_minutes',
        'overtime_approved',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'status' => ShiftStatusEnum::class,
        'is_late' => 'boolean',
        'overtime_approved' => 'boolean',
        'initial_balance' => 'decimal:2',
        'final_balance' => 'decimal:2',
    ];

    // علاقة موظف الاستقبال
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeFilter($query, ShiftFilter $filter)
    {
        return $filter->apply($query);
    }
}
