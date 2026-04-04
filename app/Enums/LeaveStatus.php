<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '申請中',
            self::APPROVED => '承認済',
            self::REJECTED => '却下',
        };
    }
}
