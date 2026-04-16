<?php

namespace App\Enums;

/**
 * 商品名称使用场景
 * 决定是使用真实名称还是安全映射名称
 */
enum MappingScenario: string
{
    case PAYMENT = 'payment';         // 支付接口 → 使用安全名称
    case LOGISTICS = 'logistics';     // 物流面单 → 使用安全名称
    case STOREFRONT = 'storefront';   // 买家前台 → 使用真实名称
    case PIXEL = 'pixel';             // Facebook Pixel → 使用真实名称
    case ADMIN = 'admin';             // 后台管理 → 两者都展示

    /**
     * 该场景下是否需要使用安全映射名称
     */
    public function useSafeName(): bool
    {
        return match ($this) {
            self::PAYMENT, self::LOGISTICS => true,
            self::STOREFRONT, self::PIXEL => false,
            self::ADMIN => false, // Admin 场景两者都展示，默认返回真实名
        };
    }
}
