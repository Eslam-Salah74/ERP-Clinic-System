<?php

namespace Modules\Reception\Enums;

enum AppointmentStatusEnum: string
{
    case PENDING = 'pending';       // تم الحجز / لم يتم الكشف بعد
    case COMPLETED = 'completed';   // تم الكشف / انتهى
    case CANCELLED = 'cancelled';   // ملغي
}
