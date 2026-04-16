<?php

namespace App\Enums;

enum RiskLevel: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;

    public function label(): string
    {
        return match ($this) {
            self::LOW => '低风险',
            self::MEDIUM => '中风险',
            self::HIGH => '高风险',
        };
    }
}
