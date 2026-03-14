<?php

namespace App\Enums;

enum WorkRuleScope: string
{
    case SYSTEM = 'SYSTEM';
    case DEPARTMENT = 'DEPARTMENT';
    case USER = 'USER';
}
