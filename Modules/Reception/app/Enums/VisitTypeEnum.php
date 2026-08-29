<?php

namespace Modules\Reception\Enums;

enum VisitTypeEnum: string
{
    case CONSULTATION = 'consultation';
    case FOLLOW_UP = 'follow_up';
    case SESSION = 'session'; 
}
