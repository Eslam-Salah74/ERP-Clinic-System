<?php

namespace Modules\Setup\Filters\Service;

use App\Filters\Filters;

class ServiceFilter extends Filters
{
    protected $var_filters = [
        'name',
        'department_id',
        'department',
        'is_active',
        'type',
        'search',
    ];

    /**
     * دالة للبحث الشامل (بالاسم)
     */
    public function search($value)
    {
        return $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%");
        });
    }

    /**
     * فلترة باسم الخدمة
     */
    public function name($value)
    {
        return $this->builder->where('name', 'like', "%{$value}%");
    }

    /**
     * فلترة برقم القسم (department_id) - تدعم قيمة واحدة أو مصفوفة
     */
    public function department_id($value)
    {
        if (is_array($value)) {
            return $this->builder->whereIn('department_id', $value);
        }
        return $this->builder->where('department_id', $value);
    }

    /**
     * دعم التسمية بـ camelCase
     */
    public function departmentId($value)
    {
        return $this->department_id($value);
    }

    /**
     * فلترة بالقسم (سواء أرسل المعرف id أو اسم القسم)
     */
    public function department($value)
    {
        if (is_numeric($value)) {
            return $this->department_id($value);
        }

        return $this->builder->whereHas('department', function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%");
        });
    }

    /**
     * فلترة بحالة التفعيل
     */
    public function is_active($value)
    {
        return $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value);
    }

    public function isActive($value)
    {
        return $this->is_active($value);
    }

    /**
     * فلترة بنوع الخدمة
     */
    public function type($value)
    {
        return $this->builder->where('type', $value);
    }
}
