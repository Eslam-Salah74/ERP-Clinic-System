<?php

namespace Modules\HR\Enums;

enum PayrollStatusEnum: string
{
    case DRAFT = 'draft';         // مسودة قيد المراجعة
    case APPROVED = 'approved';   // معتمد من الإدارة
    case PAID = 'paid';           // تم صرف المرتب
}
