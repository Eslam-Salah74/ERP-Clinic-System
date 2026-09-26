<?php

namespace Modules\Reception\Enums;

enum PatientPackageStatusEnum: string
{
    case ACTIVE = 'active';        // نشط (يوجد رصيد متاح للاستهلاك)
    case COMPLETED = 'completed';  // مكتمل (تم استهلاك كامل الرصيد)
    case EXPIRED = 'expired';      // منتهي الصلاحية
    case CANCELLED = 'cancelled';  // ملغي / مسترد

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشط',
            self::COMPLETED => 'مكتمل',
            self::EXPIRED => 'منتهي الصلاحية',
            self::CANCELLED => 'ملغي / مسترد',
        };
    }
}
