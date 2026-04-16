<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商户 API 密钥模型 — Central DB
 *
 * 对应表：jh_merchant_api_keys（central 库）
 * 用于商户通过 API 接入平台时的身份验证（HMAC-SHA256 签名等）。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property int|null $store_id
 * @property string $name
 * @property string $api_key
 * @property string $api_secret
 * @property array|null  $permissions
 * @property int    $status
 * @property int    $rate_limit
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon|null $revoked_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MerchantApiKey extends CentralModel
{
    protected $table = 'jh_merchant_api_keys';

    protected $fillable = [
        'merchant_id',
        'store_id',
        'name',
        'api_key',
        'api_secret',
        'permissions',
        'status',
        'rate_limit',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'api_secret',
    ];

    protected $casts = [
        'merchant_id'  => 'integer',
        'store_id'     => 'integer',
        'permissions'  => 'array',
        'status'       => 'integer',
        'rate_limit'   => 'integer',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
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
     * 只查询有效（启用且未过期未吊销）的密钥
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)
            ->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isActive(): bool
    {
        return $this->status === 1 && !$this->isExpired() && !$this->isRevoked();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
