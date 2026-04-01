<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case MANAGER = 'MANAGER';
    case EMPLOYEE = 'EMPLOYEE';
}
