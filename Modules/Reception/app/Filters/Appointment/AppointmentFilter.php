<?php

namespace Modules\Reception\Filters\Appointment;

use App\Filters\Filters;

class AppointmentFilter extends Filters
{
    protected $var_filters = [
        'patient_id',
        'doctor_id',
        'service_id',
        'visit_type',
        'status',
        'appointment_date',
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
