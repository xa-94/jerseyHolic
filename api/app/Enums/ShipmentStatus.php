<?php

namespace App\Enums;

enum ShipmentStatus: int
{
    case PENDING = 0;
    case PROCESSING = 1;
    case SHIPPED = 2;
    case IN_TRANSIT = 3;
    case DELIVERED = 4;
    case FAILED = 5;
    case RETURNED = 6;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待发货',
            self::PROCESSING => '处理中',
            self::SHIPPED => '已发货',
            self::IN_TRANSIT => '运输中',
            self::DELIVERED => '已签收',
            self::FAILED => '发货失败',
            self::RETURNED => '已退回',
        };
    }
}
