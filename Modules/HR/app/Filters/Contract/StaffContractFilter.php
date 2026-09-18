<?php

namespace Modules\HR\Filters\Contract;

use App\Filters\Filters;

class StaffContractFilter extends Filters
{
    protected $var_filters = [
        'user_id',
        'contract_type',
        'is_active',
        'has_target',
        'search',
    ];

    public function search($search)
    {
        return $this->builder->where(function ($query) use ($search) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
        });
    }

    public function isActive($value)
    {
        return $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
