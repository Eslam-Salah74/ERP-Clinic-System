<?php

namespace Modules\HR\Enums;

enum TargetTypeEnum: string
{
    case DOCTOR_INCOME = 'doctor_income';     // تارجت مبني على استحقاق/دخل الطبيب المحقق
    case CLINIC_REVENUE = 'clinic_revenue';   // تارجت مبني على إجمالي إيرادات المبيعات المحققة للمركز
}
