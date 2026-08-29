<?php

namespace Modules\Setup\Enums;

enum ServiceTypeEnum: string
{
    case CONSULTATION = 'consultation'; // كشف (ممنوع ربط منتجات)
    case SESSION = 'session';          // جلسة حقن/ميزو (ترتبط بمواد مخزنية)
    case DEVICE = 'device';            // جلسة أجهزة كالفركشنال (بدون مواد مخزنية مباشرة)
}
