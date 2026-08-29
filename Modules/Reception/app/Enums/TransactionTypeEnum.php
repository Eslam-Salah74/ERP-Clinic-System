<?php

namespace Modules\Reception\Enums;

enum TransactionTypeEnum: string
{
    case INCOME = 'income';     // إيراد (دخول فلوس للدرج)
    case EXPENSE = 'expense';   // مصروفات (خروج فلوس من الدرج - زي مصاريف البوفيه مثلاً)
    case REFUND = 'refund';     // مرتجع (خروج فلوس للمريض)
}
