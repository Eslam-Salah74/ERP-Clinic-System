<?php

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Inventory\Models\Item;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Filters\Service\ServiceFilter;
use Modules\Setup\Models\Department;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'services';
    protected $guarded = ['id'];
    protected $fillable = ['name', 'department_id', 'price', 'is_active', 'type'];


    protected $casts = [
        'is_active' => 'boolean',
        'type' => ServiceTypeEnum::class, // تفعيل الـ Cast هنا
    ];

    public function department()
    {
        return $this->belongsTo(Department::class,'department_id');
    }

    public function scopeFilter($query, ServiceFilter $filter)
    {
        return $filter->apply($query);
    }

    public function items()
{
    return $this->belongsToMany(Item::class, 'service_items')
                ->withPivot('quantity')
                ->withTimestamps();
}
}
