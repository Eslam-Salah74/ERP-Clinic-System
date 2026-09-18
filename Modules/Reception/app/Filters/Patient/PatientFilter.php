<?php

namespace Modules\Reception\Filters\Patient;

use App\Filters\Filters;

class PatientFilter extends Filters
{
    protected $var_filters = [
        'name',
        'phone',
        'gender',
        'is_staff',
        'created_by',
        'search',
    ];

    

    public function search($search)
    {
        return $this->builder->where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            if (is_numeric($search)) {
                $query->orWhere('id', $search);
            }
        });
    }

    public function phone($phone)
    {
        return $this->builder->where('phone', 'like', "%{$phone}%");
    }

    public function name($name)
    {
        return $this->builder->where('name', 'like', "%{$name}%");
    }

    public function gender($gender)
    {
        return $this->builder->where('gender', $gender);
    }

    public function is_staff($isStaff)
    {
        return $this->builder->where('is_staff', filter_var($isStaff, FILTER_VALIDATE_BOOLEAN));
    }

    public function created_by($userId)
    {
        return $this->builder->where('created_by', $userId);
    }
}
