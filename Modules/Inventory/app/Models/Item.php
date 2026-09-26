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
    protected $fillable = ['name', 'selling_price', 'unit', 'stock_unit', 'conversion_factor', 'type', 'current_stock', 'is_active'];

    protected static function booted(): void
    {
        static::saved(function ($item) {
            if ($item->is_active) {
                try {
                    app(\Modules\Setup\Services\Notification\NotificationService::class)->notifyLowStockForItem($item);
                } catch (\Throwable $e) {
                    // Silently fail during migrations/seeding if dependencies are not ready
                }
            }
        });
    }

    protected $casts = [
        'type' => ItemTypeEnum::class,
        'unit' => ItemUnitEnum::class,
        'stock_unit' => ItemUnitEnum::class,
    ];
    public function scopeFilter($query, ItemFilter $filter)
    {
        return $filter->apply($query);
    }

    public function purchaseItems()
    {
        return $this->hasMany(\Modules\Inventory\Models\PurchaseInvoiceItem::class, 'item_id');
    }
}
