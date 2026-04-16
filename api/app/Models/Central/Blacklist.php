<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 黑名单模型 — Central DB
 *
 * 对应表：jh_blacklist（central 库）
 * 支持平台级 + 商户级双维度风控，覆盖 IP、邮箱、设备指纹、支付账号等维度。
 *
 * @property int              $id
 * @property string           $scope
 * @property int|null         $merchant_id
 * @property string           $dimension
 * @property string           $value
 * @property string           $reason
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 */
class Blacklist extends CentralModel
{
    protected $table = 'blacklist';

    /** 作用范围 */
    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_MERCHANT = 'merchant';

    /** 维度 */
    public const DIMENSION_IP              = 'ip';
    public const DIMENSION_EMAIL           = 'email';
    public const DIMENSION_DEVICE          = 'device';
    public const DIMENSION_PAYMENT_ACCOUNT = 'payment_account';

    public const DIMENSIONS = [
        self::DIMENSION_IP,
        self::DIMENSION_EMAIL,
        self::DIMENSION_DEVICE,
        self::DIMENSION_PAYMENT_ACCOUNT,
    ];

    protected $fillable = [
        'scope',
        'merchant_id',
        'dimension',
        'value',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'expires_at'  => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属商户（scope=merchant 时有值）
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 有效条目（未过期）
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 按维度筛选
     */
    public function scopeOfDimension(Builder $query, string $dimension): Builder
    {
        return $query->where('dimension', $dimension);
    }

    /**
     * 平台级黑名单
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_PLATFORM);
    }

    /**
     * 商户级黑名单
     */
    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('scope', self::SCOPE_MERCHANT)
                     ->where('merchant_id', $merchantId);
    }

    /**
     * 风控实时拦截查询：按维度+值匹配
     */
    public function scopeMatch(Builder $query, string $dimension, string $value): Builder
    {
        return $query->where('dimension', $dimension)->where('value', $value);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPlatformLevel(): bool
    {
        return $this->scope === self::SCOPE_PLATFORM;
    }
}
