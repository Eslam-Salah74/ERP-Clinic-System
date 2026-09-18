<?php

namespace Modules\Setup\Enums;

enum ServiceTypeEnum: string
{
    case CONSULTATION = 'consultation'; // كشف (ممنوع ربط منتجات)
    case SESSION = 'session';          // جلسة حقن/ميزو (ترتبط بمواد مخزنية)
    case DEVICE = 'device';            // جلسة أجهزة كالفركشنال والليزر (ترتبط بمواد مخزنية ومستهلكات مثل التيبس أو المواد)

    /**
     * هل النوع يقبل ربط مواد مخزنية
     */
    public function allowsItems(): bool
    {
        return $this !== self::CONSULTATION;
    }
}
