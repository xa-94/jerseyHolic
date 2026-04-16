<?php

namespace App\Enums;

enum CouponType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
    case FREE_SHIPPING = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => '固定金额',
            self::PERCENTAGE => '百分比折扣',
            self::FREE_SHIPPING => '免运费',
        };
    }
}
