<?php

namespace App\Enums;

enum PaymentAccountPermission: int
{
    case CAN_RECEIVE = 1;
    case SUSPENDED = 2;
    case BANNED = 3;

    public function label(): string
    {
        return match ($this) {
            self::CAN_RECEIVE => '可收款',
            self::SUSPENDED => '暂停',
            self::BANNED => '已封禁',
        };
    }
}
