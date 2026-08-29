<?php

namespace Modules\Inventory\Enums;

enum ItemTypeEnum: string
{
    case CONSUMABLE = 'consumable'; // مستهلكات طبية وحقن (سعر البيع 0 - تُباع داخل الجلسات)
    case RETAILABLE = 'retailable'; // منتجات صيدلية وتجزئة (لها سعر بيع مباشر)
}
