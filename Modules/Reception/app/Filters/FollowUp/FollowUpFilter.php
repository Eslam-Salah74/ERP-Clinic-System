<?php

namespace Modules\Reception\Filters\FollowUp;

use App\Filters\Filters;

class FollowUpFilter extends Filters
{
    protected $var_filters = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'shift_id',
        'status',
        'follow_up_date',
        'search',
    ];

    public function search($search)
    {
        return $this->builder->whereHas('patient', function ($query) use ($search) {
            $query->where('name', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
        });
    }
}
