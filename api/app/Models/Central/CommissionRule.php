<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 佣金规则模型 — Central DB
 *
 * 对应表：jh_commission_rules（central 库）
 * 支持三级优先级：站点 > 商户 > 全局（兜底）。
 * 实际费率 = max(min_rate, min(max_rate, base_rate - volume_discount - loyalty_discount))
 *
 * @property int              $id
 * @property int|null         $merchant_id
 * @property int|null         $store_id
 * @property string           $rule_type
 * @property string           $tier_name
 * @property string           $base_rate
 * @property string           $volume_discount
 * @property string           $loyalty_discount
 * @property string           $min_rate
 * @property string           $max_rate
 * @property int              $enabled
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 */
class CommissionRule extends CentralModel
{
    protected $table = 'commission_rules';

    /** 规则类型常量 */
    public const RULE_TYPE_DEFAULT = 'default';
    public const RULE_TYPE_VIP     = 'vip';
    public const RULE_TYPE_PROMO   = 'promo';

    /** 启用状态 */
    public const ENABLED  = 1;
    public const DISABLED = 0;

    protected $fillable = [
        'merchant_id',
        'store_id',
        'rule_type',
        'tier_name',
        'base_rate',
        'volume_discount',
        'loyalty_discount',
        'min_rate',
        'max_rate',
        'enabled',
    ];

    protected $casts = [
        'merchant_id'      => 'integer',
        'store_id'         => 'integer',
        'base_rate'        => 'decimal:2',
        'volume_discount'  => 'decimal:2',
        'loyalty_discount' => 'decimal:2',
        'min_rate'         => 'decimal:2',
        'max_rate'         => 'decimal:2',
        'enabled'          => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 仅启用的规则
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', self::ENABLED);
    }

    /**
     * 全局规则（merchant_id IS NULL AND store_id IS NULL）
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('merchant_id')->whereNull('store_id');
    }

    /**
     * 商户级规则
     */
    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId)->whereNull('store_id');
    }

    /**
     * 站点级规则
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 计算实际费率
     *
     * 实际费率 = max(min_rate, min(max_rate, base_rate - volume_discount - loyalty_discount))
     */
    public function calculateEffectiveRate(): float
    {
        $rate = (float) $this->base_rate - (float) $this->volume_discount - (float) $this->loyalty_discount;

        return max((float) $this->min_rate, min((float) $this->max_rate, $rate));
    }
}
