<?php

namespace Modules\HR\Enums;

enum ExpenseCategoryEnum: string
{
    case UTILITY = 'utility';           // فواتير الكهرباء، المياه، النت، الهاتف
    case BUFFET = 'buffet';             // مستلزمات البوفيه والضيافة
    case MAINTENANCE = 'maintenance';   // صيانة، نظافة، أدوات مكتبية
    case RENT = 'rent';                 // إيجارات
    case SALARIES = 'salaries';         // مكافآت و مرتبات
    case OTHER = 'other';               // مصروفات متنوعة 
}
