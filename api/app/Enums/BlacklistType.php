<?php

namespace App\Enums;

enum BlacklistType: string
{
    case IP = 'ip';
    case EMAIL = 'email';
    case PAYMENT_ACCOUNT = 'payment_account';
    case PHONE = 'phone';
    case ADDRESS = 'address';

    public function label(): string
    {
        return match ($this) {
            self::IP => 'IP地址',
            self::EMAIL => '邮箱',
            self::PAYMENT_ACCOUNT => '支付账号',
            self::PHONE => '手机号',
            self::ADDRESS => '收货地址',
        };
    }
}
