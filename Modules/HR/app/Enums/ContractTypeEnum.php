<?php

namespace Modules\HR\Enums;

enum ContractTypeEnum: string
{
    case DOCTOR = 'doctor';
    case NURSE = 'nurse';
    case RECEPTIONIST = 'receptionist';
    case GENERAL = 'general';
}
