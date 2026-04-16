<?php

namespace App\Enums;

enum OrderDisputeStatus: int
{
    case NONE = 1;
    case OPEN = 2;
    case RESOLVED = 3;

    public function label(): string
    {
        return match ($this) {
            self::NONE => '无纠纷',
            self::OPEN => '有纠纷',
            self::RESOLVED => '纠纷结束',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::NONE => 'No Dispute',
            self::OPEN => 'Dispute Open',
            self::RESOLVED => 'Dispute Resolved',
        };
    }
}
