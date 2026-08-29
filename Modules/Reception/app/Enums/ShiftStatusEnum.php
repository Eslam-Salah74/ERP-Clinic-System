<?php

namespace Modules\Reception\Enums;

enum ShiftStatusEnum: string
{
    case OPEN = 'open';       // شفت مفتوح
    case CLOSED = 'closed';   // شفت مغلق
}
