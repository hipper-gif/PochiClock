<?php

namespace App\Enums;

enum WorkRuleScope: string
{
    case SYSTEM = 'SYSTEM';
    case JOB_GROUP = 'JOB_GROUP';
    case USER = 'USER';
}
