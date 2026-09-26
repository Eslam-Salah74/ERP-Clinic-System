<?php

namespace Modules\Reception\Enums;

enum InvoiceItemTypeEnum: string
{
    case SERVICE = 'service';                         // خدمة طبية
    case PRODUCT = 'product';                         // منتج مخزني
    case PACKAGE = 'package';                         // شراء باقة
    case PACKAGE_CONSUMPTION = 'package_consumption'; // استهلاك جلسة من باقة (بقيمة 0 ج)
    case PACKAGE_DEBT_PAYMENT = 'package_debt_payment'; // سداد مديونية/قسط باقة

    public function label(): string
    {
        return match ($this) {
            self::SERVICE => 'خدمة',
            self::PRODUCT => 'منتج',
            self::PACKAGE => 'باقة',
            self::PACKAGE_CONSUMPTION => 'استهلاك باقة',
            self::PACKAGE_DEBT_PAYMENT => 'سداد قسط باقة',
        };
    }
}
