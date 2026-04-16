<?php

namespace App\Enums;

enum PaymentAccountStatus: int
{
    case DISABLED = 0;
    case ENABLED = 1;

    public function label(): string
    {
        return match ($this) {
            self::DISABLED => '禁用',
            self::ENABLED => '启用',
        };
    }
}
