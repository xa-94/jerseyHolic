<?php

namespace App\Enums;

enum SkuCategory: string
{
    case IMITATION = 'hic';
    case FOREIGN_TRADE = 'WPZ';
    case CUSTOM = 'DIY';
    case NBL = 'NBL';
    case UNKNOWN = 'unknown';

    public static function fromSku(string $sku): self
    {
        $sku = trim($sku);
        if (strlen($sku) <= 3) {
            return self::UNKNOWN;
        }

        $upper = strtoupper($sku);
        if (str_starts_with($upper, 'HIC')) {
            return self::IMITATION;
        }
        if (str_starts_with($upper, 'WPZ')) {
            return self::FOREIGN_TRADE;
        }
        if (str_starts_with($upper, 'DIY')) {
            return self::CUSTOM;
        }
        if (str_starts_with($upper, 'NBL')) {
            return self::NBL;
        }

        return self::UNKNOWN;
    }

    public function needsSafeNameReplacement(): bool
    {
        return match ($this) {
            self::IMITATION => true,
            self::FOREIGN_TRADE => false,
            self::CUSTOM => true,
            self::NBL => true,
            self::UNKNOWN => true,
        };
    }

    public function defaultSafeName(): string
    {
        return match ($this) {
            self::IMITATION => 'Sports Jersey',
            self::FOREIGN_TRADE => '',
            self::CUSTOM => 'Custom Print Shirt',
            self::NBL => 'Sports Training Jersey',
            self::UNKNOWN => 'Sports Training Jersey',
        };
    }
}
