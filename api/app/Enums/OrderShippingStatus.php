<?php

namespace App\Enums;

enum OrderShippingStatus: int
{
    case UNPROCESSED = 0;
    case PENDING_PICK = 1;
    case PICKING = 3;
    case PICK_COMPLETED = 8;
    case COLLECTED = 9;

    public function label(): string
    {
        return match ($this) {
            self::UNPROCESSED => '未处理',
            self::PENDING_PICK => '待配货',
            self::PICKING => '配货中',
            self::PICK_COMPLETED => '配货完成',
            self::COLLECTED => '物流已揽收',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::UNPROCESSED => 'Unprocessed',
            self::PENDING_PICK => 'Pending Pick',
            self::PICKING => 'Picking',
            self::PICK_COMPLETED => 'Pick Completed',
            self::COLLECTED => 'Collected',
        };
    }
}
