<?php

namespace App\Enums;

enum MappingType: string
{
    case EXACT = 'exact';
    case PREFIX = 'prefix';
    case DEFAULT = 'default';

    public function label(): string
    {
        return match ($this) {
            self::EXACT => '精确映射',
            self::PREFIX => 'SKU前缀通用名',
            self::DEFAULT => '兜底默认名',
        };
    }
}
