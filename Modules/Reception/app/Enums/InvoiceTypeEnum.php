<?php

namespace Modules\Reception\Enums;

enum InvoiceTypeEnum: string
{
    case CONSULTATION = 'consultation'; // كشف / استشارة (يتطلب دكتور)
    case SESSION = 'session';           // جلسة طبية (يتطلب دكتور وممكن ممرضة)
    case FOLLOW_UP = 'follow_up';       // متابعة (يتطلب دكتور)
    case DIRECT_SALE = 'direct_sale';   // بيع مباشر أدوية/مستلزمات (لا يتطلب دكتور)
}
