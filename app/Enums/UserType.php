<?php

namespace App\Enums;

enum UserType: string
{
    case ADMIN = 'admin';
    case RECEPTIONIST = 'receptionist';
    case DOCTOR = 'doctor';
    case NURSE = 'nurse';
    case STERILIZATION = 'sterilization';
    case ACCOUNTANT = 'accountant';
}
