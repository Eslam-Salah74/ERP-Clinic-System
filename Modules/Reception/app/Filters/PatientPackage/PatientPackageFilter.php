<?php

namespace Modules\Reception\Filters\PatientPackage;

use App\Filters\Filters;

class PatientPackageFilter extends Filters
{
    protected $var_filters = [
        'patient_id',
        'package_id',
        'status',
        'search',
    ];

    public function search($value)
    {
        return $this->builder->whereHas('patient', function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%")
                  ->orWhere('phone', 'like', "%{$value}%");
        })->orWhereHas('package', function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%");
        });
    }

    public function patient_id($value)
    {
        return $this->builder->where('patient_id', $value);
    }

    public function package_id($value)
    {
        return $this->builder->where('package_id', $value);
    }

    public function status($value)
    {
        return $this->builder->where('status', $value);
    }
}
