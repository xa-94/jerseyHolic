<?php

namespace App\Enums;

enum ProductStatus: int
{
    case DISABLED = 0;
    case ENABLED = 1;
    case DRAFT = 2;

    public function label(): string
    {
        return match ($this) {
            self::DISABLED => '下架',
            self::ENABLED => '上架',
            self::DRAFT => '草稿',
        };
    }
}
