<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessException;
use App\Models\Central\Store;
use App\Models\Central\StoreProductConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 站点商品配置服务
 *
 * 管理每个站点的商品展示差异化配置：价格覆盖、安全名称、展示语言等。
 * 配置使用 Redis 缓存（30 分钟），更新时自动清除。
 */
class StoreProductConfigService
{
    /**
     * 缓存前缀
     */
    private const CACHE_PREFIX = 'store_product_config:';

    /**
     * 缓存时长（秒）：30 分钟
     */
    private const CACHE_TTL = 1800;

    /**
     * 默认展示设置
     */
    private const DEFAULT_DISPLAY_SETTINGS = [
        'language'           => 'en',
        'currency'           => 'USD',
        'placeholder_image'  => null,
        'safe_name_enabled'  => false,
        'auto_translate'     => true,
    ];

    /* ================================================================
     |  查询
     | ================================================================ */

    /**
     * 获取站点商品配置（带 Redis 缓存）
     *
     * @param  string $storeId 站点 ID
     * @return StoreProductConfig|null
     */
    public function getConfig(string $storeId): ?StoreProductConfig
    {
        $cacheKey = self::CACHE_PREFIX . $storeId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($storeId) {
            return StoreProductConfig::query()
                ->ofStore($storeId)
                ->active()
                ->first();
        });
    }

    /* ================================================================
     |  写入
     | ================================================================ */

    /**
     * 更新站点商品配置（upsert）
     *
     * 不存在则创建，存在则更新。操作完成后清除缓存。
     *
     * @param  string $storeId 站点 ID
     * @param  array  $data    配置数据
     * @return StoreProductConfig
     *
     * @throws BusinessException 站点不存在时抛出
     */
    public function updateConfig(string $storeId, array $data): StoreProductConfig
    {
        // 验证站点存在
        $store = Store::find($storeId);
        if (!$store) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '站点不存在');
        }

        $config = StoreProductConfig::updateOrCreate(
            ['store_id' => $storeId],
            $data,
        );

        $this->clearCache($storeId);

        Log::info('[StoreProductConfig] 配置已更新', [
            'store_id' => $storeId,
            'fields'   => array_keys($data),
        ]);

        return $config->fresh();
    }

    /* ================================================================
     |  价格覆盖
     | ================================================================ */

    /**
     * 应用价格覆盖
     *
     * 获取站点配置 → 计算覆盖后价格 → 返回结果。
     * 若站点无配置或未启用价格覆盖，返回原价。
     *
     * @param  string $storeId   站点 ID
     * @param  string $basePrice 基础价格（bcmath 字符串）
     * @return string 覆盖后价格（2 位小数）
     */
    public function applyPriceOverride(string $storeId, string $basePrice): string
    {
        $config = $this->getConfig($storeId);

        if ($config === null) {
            return bcadd($basePrice, '0', 2);
        }

        return $config->getPriceOverride($basePrice);
    }

    /* ================================================================
     |  展示设置
     | ================================================================ */

    /**
     * 获取有效展示设置
     *
     * 合并默认配置和站点覆盖配置，返回最终生效的展示参数。
     *
     * @param  string $storeId 站点 ID
     * @return array{language: string, currency: string, placeholder_image: string|null, safe_name_enabled: bool, auto_translate: bool}
     */
    public function getEffectiveDisplaySettings(string $storeId): array
    {
        $config = $this->getConfig($storeId);

        if ($config === null) {
            return self::DEFAULT_DISPLAY_SETTINGS;
        }

        return [
            'language'          => $config->getEffectiveLanguage(),
            'currency'          => $config->display_currency ?? self::DEFAULT_DISPLAY_SETTINGS['currency'],
            'placeholder_image' => $config->custom_placeholder_image,
            'safe_name_enabled' => $config->safe_name_override_enabled,
            'auto_translate'    => $config->auto_translate,
        ];
    }

    /* ================================================================
     |  缓存管理
     | ================================================================ */

    /**
     * 清除指定站点的配置缓存
     *
     * @param string $storeId 站点 ID
     */
    public function clearCache(string $storeId): void
    {
        Cache::forget(self::CACHE_PREFIX . $storeId);
    }
}
