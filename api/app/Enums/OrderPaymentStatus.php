<?php

namespace App\Enums;

enum OrderPaymentStatus: int
{
    case PENDING = 1;
    case FAILED = 2;
    case PAID = 3;
    case CANCELLED = 4;
    case PARTIAL_REFUNDED = 5;
    case REFUNDED = 6;
    case PROCESSING = 7;
    case PARTIAL_REFUNDING = 8;
    case REFUNDING = 9;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待支付',
            self::FAILED => '支付失败',
            self::PAID => '已支付',
            self::CANCELLED => '已取消',
            self::PARTIAL_REFUNDED => '部分退款',
            self::REFUNDED => '已退款',
            self::PROCESSING => '交易中',
            self::PARTIAL_REFUNDING => '部分退款中',
            self::REFUNDING => '退款中',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::FAILED => 'Failed',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
            self::PARTIAL_REFUNDED => 'Partial Refunded',
            self::REFUNDED => 'Refunded',
            self::PROCESSING => 'Processing',
            self::PARTIAL_REFUNDING => 'Partial Refunding',
            self::REFUNDING => 'Refunding',
        };
    }
}
