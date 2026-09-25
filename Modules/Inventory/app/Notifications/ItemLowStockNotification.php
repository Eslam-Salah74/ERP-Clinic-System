<?php

namespace Modules\Inventory\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Inventory\Models\Item;

class ItemLowStockNotification extends Notification
{
    use Queueable;

    public Item $item;
    public float $threshold;

    public function __construct(Item $item, float $threshold)
    {
        $this->item = $item;
        $this->threshold = $threshold;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $unit = $this->item->unit instanceof \BackedEnum ? $this->item->unit->value : (string) $this->item->unit;

        return [
            'title'         => 'تنبيه: انخفاض رصيد المخزون',
            'message'       => "تنبيه انخفاض المخزون: الصنف ({$this->item->name}) وصل رصيده الحالي إلى ({$this->item->current_stock} {$unit}) وهو أقل من أو يساوي الحد الأدنى المحدد في الإعدادات ({$this->threshold} {$unit}).",
            'type'          => 'low_stock',
            'item_id'       => $this->item->id,
            'item_name'     => $this->item->name,
            'current_stock' => (float) $this->item->current_stock,
            'threshold'     => (float) $this->threshold,
            'unit'          => $unit,
        ];
    }
}
