<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Inventory\Enums\ItemTypeEnum;
use Modules\Inventory\Enums\ItemUnitEnum;
use Modules\Inventory\Filters\Item\ItemFilter;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'items';
    protected $guarded = ['id'];
    protected $fillable = ['name', 'selling_price','unit','type', 'current_stock', 'is_active'];

    protected $casts = [
        'type' => ItemTypeEnum::class,
        'unit' => ItemUnitEnum::class,
    ];
    public function scopeFilter($query, ItemFilter $filter)
    {
        return $filter->apply($query);
    }
}
