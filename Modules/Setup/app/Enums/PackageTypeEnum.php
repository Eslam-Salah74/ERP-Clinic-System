<?php

namespace Modules\Setup\Enums;

enum PackageTypeEnum: string
{
    case SESSIONS = 'sessions';          // باقة جلسات (عدد جلسات لخدمة واحدة أو خدمات متعددة)
    case PULSES = 'pulses';              // باقة نبضات ليزر (رصيد نبضات)
    case UNITS_VOLUME = 'units_volume';  // باقة كميات/ملي (فيلر، بوتوكس، خيوط، ميزو)
    case MIXED = 'mixed';                // باقة مشتركة متنوعة

    public function label(): string
    {
        return match ($this) {
            self::SESSIONS => 'باقة جلسات',
            self::PULSES => 'باقة نبضات ليزر',
            self::UNITS_VOLUME => 'باقة كميات وميلي',
            self::MIXED => 'باقة منوعة',
        };
    }
}
