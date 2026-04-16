<?php

namespace App\Enums;

enum OrderRefundStatus: int
{
    case NONE = 1;
    case PARTIAL_REFUNDED = 5;
    case REFUNDED = 6;
    case PARTIAL_REFUNDING = 8;
    case REFUNDING = 9;

    public function label(): string
    {
        return match ($this) {
            self::NONE => '未退款',
            self::PARTIAL_REFUNDED => '部分退款',
            self::REFUNDED => '已退款',
            self::PARTIAL_REFUNDING => '部分退款中',
            self::REFUNDING => '退款中',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::NONE => 'No Refund',
            self::PARTIAL_REFUNDED => 'Partial Refunded',
            self::REFUNDED => 'Refunded',
            self::PARTIAL_REFUNDING => 'Partial Refunding',
            self::REFUNDING => 'Refunding',
        };
    }
}
