<?php

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Setup\Filters\Department\DepartmentFilter;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'departments';
    protected $guarded = ['id'];

    protected $fillable = ['name', 'is_active'];

    public function scopeFilter($query, DepartmentFilter $filter)
    {
        return $filter->apply($query);
    }
}
