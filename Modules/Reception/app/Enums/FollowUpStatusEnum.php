<?php

namespace Modules\Reception\Enums;

enum FollowUpStatusEnum: string
{
    case PENDING = 'pending';       // قيد الانتظار / لم تتم المتابعة بعد
    case COMPLETED = 'completed';   // تمت المتابعة بنجاح
    case CANCELLED = 'cancelled';   // ملغاة
}
