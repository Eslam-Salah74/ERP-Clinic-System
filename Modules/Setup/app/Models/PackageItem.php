<?php

namespace Modules\Setup\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Models\Item;
use Modules\Setup\Enums\PackageItemTypeEnum;

class PackageItem extends Model
{
    use HasFactory;

    protected $table = 'package_items';
    protected $guarded = ['id'];

    protected $fillable = [
        'package_id',
        'item_type',
        'service_id',
        'product_id',
        'quantity',
        'unit',
        'custom_name',
    ];

    protected $casts = [
        'item_type' => PackageItemTypeEnum::class,
        'quantity' => 'decimal:2',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }
}
