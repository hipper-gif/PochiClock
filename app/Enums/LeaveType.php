<?php

namespace App\Enums;

enum LeaveType: string
{
    case FULL = 'full';
    case HALF_AM = 'half_am';
    case HALF_PM = 'half_pm';

    public function label(): string
    {
        return match ($this) {
            self::FULL => '全休',
            self::HALF_AM => '半休（午前）',
            self::HALF_PM => '半休（午後）',
        };
    }

    public function consumeDays(): float
    {
        return $this === self::FULL ? 1.0 : 0.5;
    }
}
