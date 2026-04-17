<?php

declare(strict_types=1);

namespace App\Models\Merchant;

use Illuminate\Database\Eloquent\Builder;

/**
 * 商品同步规则模型 — Merchant DB
 *
 * 对应表：sync_rules（商户独立库，无 jh_ 前缀）
 * 定义主商品同步到各店铺的策略，包括目标站点、同步字段、价格策略等。
 *
 * @property int        $id
 * @property string     $name
 * @property array      $target_store_ids
 * @property array|null $excluded_store_ids
 * @property array      $sync_fields
 * @property string     $price_strategy
 * @property string     $price_multiplier
 * @property bool       $auto_sync
 * @property int        $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SyncRule extends MerchantModel
{
    protected $table = 'sync_rules';

    /* ----------------------------------------------------------------
     |  常量
     | ---------------------------------------------------------------- */

    /** @var string 价格策略：固定价格（直接使用 base_price） */
    public const PRICE_FIXED = 'fixed';

    /** @var string 价格策略：乘数（base_price × price_multiplier） */
    public const PRICE_MULTIPLIER = 'multiplier';

    /** @var string 价格策略：自定义 */
    public const PRICE_CUSTOM = 'custom';

    /** @var int 状态：启用 */
    public const STATUS_ENABLED = 1;

    /** @var int 状态：禁用 */
    public const STATUS_DISABLED = 0;

    /* ----------------------------------------------------------------
     |  属性
     | ---------------------------------------------------------------- */

    protected $fillable = [
        'name',
        'target_store_ids',
        'excluded_store_ids',
        'sync_fields',
        'price_strategy',
        'price_multiplier',
        'auto_sync',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'target_store_ids'   => 'json',
        'excluded_store_ids' => 'json',
        'sync_fields'        => 'json',
        'price_multiplier'   => 'decimal:2',
        'auto_sync'          => 'boolean',
        'status'             => 'integer',
        'last_synced_at'     => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 筛选启用的规则
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 筛选自动同步的规则
     */
    public function scopeAutoSync(Builder $query): Builder
    {
        return $query->where('auto_sync', true);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 判断规则是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 判断给定的 Store ID 是否在同步目标中（且不在排除列表中）
     */
    public function appliesToStore(int $storeId): bool
    {
        $targets  = $this->target_store_ids ?? [];
        $excluded = $this->excluded_store_ids ?? [];

        return in_array($storeId, $targets, true)
            && !in_array($storeId, $excluded, true);
    }

    /**
     * 根据价格策略计算同步后价格
     */
    public function calculatePrice(string $basePrice): string
    {
        return match ($this->price_strategy) {
            self::PRICE_MULTIPLIER => bcmul($basePrice, (string) $this->price_multiplier, 2),
            default                => $basePrice,
        };
    }
}
