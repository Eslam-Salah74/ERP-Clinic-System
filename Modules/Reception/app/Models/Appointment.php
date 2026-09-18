<?php

namespace Modules\Reception\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Inventory\Models\Item;
use Modules\Reception\Enums\AppointmentStatusEnum;
use Modules\Reception\Enums\VisitTypeEnum;
use Modules\Reception\Filters\Appointment\AppointmentFilter;
use Modules\Reception\Models\Shift;
use Modules\Setup\Models\Service;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'appointments';
    protected $guarded = ['id'];
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'shift_id',
        'nurse_id',
        'service_id',
        'service_items_ids',
        'appointment_date',
        'visit_type',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'appointment_date' => 'datetime',
        'visit_type' => VisitTypeEnum::class,
        'status' => AppointmentStatusEnum::class,
        'service_items_ids' => 'array',
    ];

    // --- العلاقات (Relationships) ---

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function getServiceItemsAttribute()
    {
        $ids = $this->service_items_ids;
        if (empty($ids) || !is_array($ids)) {
            return collect();
        }

        if ($this->relationLoaded('service') && $this->service && $this->service->relationLoaded('items')) {
            $matchingItems = $this->service->items->filter(function ($item) use ($ids) {
                return in_array($item->id, $ids) || (isset($item->pivot->id) && in_array($item->pivot->id, $ids));
            })->values();
            if ($matchingItems->isNotEmpty()) {
                return $matchingItems;
            }
        }

        if ($this->service_id) {
            $service = $this->relationLoaded('service') ? $this->service : Service::with('items')->find($this->service_id);
            if ($service && $service->relationLoaded('items')) {
                $matchingItems = $service->items->filter(function ($item) use ($ids) {
                    return in_array($item->id, $ids) || (isset($item->pivot->id) && in_array($item->pivot->id, $ids));
                })->values();
                if ($matchingItems->isNotEmpty()) {
                    return $matchingItems;
                }
            }
        }

        return Item::whereIn('id', $ids)->get();
    }

    // --- الفلاتر (Filters) ---
    public function scopeFilter($query, AppointmentFilter $filter)
    {
        return $filter->apply($query);
    }
}
