<?php

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Setup\Enums\PackageTypeEnum;
use Modules\Setup\Filters\Package\PackageFilter;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'packages';
    protected $guarded = ['id'];

    protected $fillable = [
        'department_id',
        'name',
        'type',
        'original_price',
        'price',
        'validity_days',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'type' => PackageTypeEnum::class,
        'original_price' => 'decimal:2',
        'price' => 'decimal:2',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function items()
    {
        return $this->hasMany(PackageItem::class, 'package_id');
    }

    public function scopeFilter($query, PackageFilter $filter)
    {
        return $filter->apply($query);
    }
}
