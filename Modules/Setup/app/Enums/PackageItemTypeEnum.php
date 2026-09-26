<?php

namespace Modules\Setup\Enums;

enum PackageItemTypeEnum: string
{
    case SERVICE = 'service';   // جلسة خدمة طبية (ليزر نص ايد، دقن، فراكشنال، كشف...)
    case PULSE = 'pulse';       // نبضات ليزر
    case PRODUCT = 'product';   // مادة مخزنية/حقن (فيلر، بوتوكس، خيوط، ميزو)

    public function label(): string
    {
        return match ($this) {
            self::SERVICE => 'جلسة خدمة',
            self::PULSE => 'نبضات',
            self::PRODUCT => 'مادة مخزنية / حقن',
        };
    }
}
