<?php

namespace Modules\HR\Enums;

enum CommissionTypeEnum: string
{
    case FIXED = 'fixed';             // مبلغ ثابت للخدمة
    case PERCENTAGE = 'percentage';   // نسبة مئوية من الخدمة
}
