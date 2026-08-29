<?php

namespace Modules\Reception\Enums;

enum InvoiceStatusEnum: string
{
    case PAID = 'paid';           // مدفوعة بالكامل (دخلت الشفت)
    case UNPAID = 'unpaid';       // غير مدفوعة (في حالة التأمين أو الآجل مستقبلاً)
    case REFUNDED = 'refunded';   // مستردة (تم إرجاع الفلوس للمريض)
    case CANCELLED = 'cancelled'; // ملغاة (خطأ من الموظف قبل الدفع)
}
