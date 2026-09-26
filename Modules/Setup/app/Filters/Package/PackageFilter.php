<?php

namespace Modules\Setup\Filters\Package;

use App\Filters\Filters;

class PackageFilter extends Filters
{
    protected $var_filters = [
        'name',
        'department_id',
        'is_active',
        'type',
        'search',
    ];

    public function search($value)
    {
        return $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%");
        });
    }

    public function name($value)
    {
        return $this->builder->where('name', 'like', "%{$value}%");
    }

    public function department_id($value)
    {
        if (is_array($value)) {
            return $this->builder->whereIn('department_id', $value);
        }
        return $this->builder->where('department_id', $value);
    }

    public function is_active($value)
    {
        return $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value);
    }

    public function type($value)
    {
        return $this->builder->where('type', $value);
    }
}
