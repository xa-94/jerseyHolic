<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 站点商品配置模型 — Central DB
 *
 * 对应表：store_product_configs（central 库，无 jh_ 前缀）
 * 每个站点（Store）可配置独立的价格覆盖策略、安全名称、展示语言等。
 *
 * @property int         $id
 * @property string      $store_id
 * @property bool        $price_override_enabled
 * @property string|null $price_override_strategy  multiplier|fixed|markup
 * @property string|null $price_override_value
 * @property bool        $safe_name_override_enabled
 * @property string|null $custom_placeholder_image
 * @property string|null $display_currency
 * @property bool        $auto_translate
 * @property string      $default_language
 * @property int         $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class StoreProductConfig extends CentralModel
{
    protected $table = 'store_product_configs';

    protected $fillable = [
        'store_id',
        'price_override_enabled',
        'price_override_strategy',
        'price_override_value',
        'safe_name_override_enabled',
        'custom_placeholder_image',
        'display_currency',
        'auto_translate',
        'default_language',
        'status',
    ];

    protected $casts = [
        'price_override_enabled'     => 'boolean',
        'price_override_value'       => 'decimal:2',
        'safe_name_override_enabled' => 'boolean',
        'auto_translate'             => 'boolean',
        'status'                     => 'integer',
    ];

    /* ================================================================
     |  关系
     | ================================================================ */

    /**
     * 所属站点
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /* ================================================================
     |  Scopes
     | ================================================================ */

    /**
     * 仅启用的配置
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 指定站点的配置
     */
    public function scopeOfStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /* ================================================================
     |  业务方法
     | ================================================================ */

    /**
     * 计算价格覆盖后的价格
     *
     * 策略说明：
     * - multiplier: basePrice × value（如 value=1.2 → 加价 20%）
     * - fixed:      直接返回 value 作为固定价格
     * - markup:     basePrice + value（固定加价金额）
     *
     * @param  string $basePrice 基础价格（字符串，兼容 bcmath）
     * @return string 覆盖后价格（2 位小数）
     */
    public function getPriceOverride(string $basePrice): string
    {
        if (!$this->price_override_enabled || $this->price_override_strategy === null) {
            return bcadd($basePrice, '0', 2);
        }

        $value = (string) $this->price_override_value;

        return match ($this->price_override_strategy) {
            'multiplier' => bcmul($basePrice, $value, 2),
            'fixed'      => bcadd($value, '0', 2),
            'markup'     => bcadd($basePrice, $value, 2),
            default      => bcadd($basePrice, '0', 2),
        };
    }

    /**
     * 获取有效语言
     *
     * 优先返回站点配置的 default_language，否则回退 'en'。
     */
    public function getEffectiveLanguage(): string
    {
        return $this->default_language ?: 'en';
    }
}
