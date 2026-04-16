<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商户 API 密钥模型 — Central DB（RSA 方案）
 *
 * 对应表：merchant_api_keys（central 库）
 * 采用 RSA 非对称加密方案管理商户 API 密钥。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property int|null $store_id
 * @property string $key_id          公开的密钥标识符
 * @property string $public_key      RSA 公钥（PEM 格式）
 * @property string $algorithm       签名算法，默认 RSA-SHA256
 * @property int    $key_size        密钥长度，默认 4096
 * @property string $status          active|rotating|revoked|expired
 * @property \Carbon\Carbon|null $activated_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $revoked_at
 * @property string|null $revoke_reason
 * @property string|null $download_token
 * @property \Carbon\Carbon|null $download_token_expires_at
 * @property \Carbon\Carbon|null $downloaded_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MerchantApiKey extends CentralModel
{
    protected $table = 'merchant_api_keys';

    protected $fillable = [
        'merchant_id',
        'store_id',
        'key_id',
        'public_key',
        'algorithm',
        'key_size',
        'status',
        'activated_at',
        'expires_at',
        'revoked_at',
        'revoke_reason',
        'download_token',
        'download_token_expires_at',
        'downloaded_at',
    ];

    protected $hidden = [
        'download_token',
    ];

    protected $casts = [
        'merchant_id'               => 'integer',
        'store_id'                  => 'integer',
        'key_size'                  => 'integer',
        'status'                    => 'string',
        'activated_at'              => 'datetime',
        'expires_at'                => 'datetime',
        'revoked_at'                => 'datetime',
        'download_token_expires_at' => 'datetime',
        'downloaded_at'             => 'datetime',
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
     * 只查询状态为 active 且未过期的密钥
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * 只查询状态为 rotating 的密钥
     */
    public function scopeRotating(Builder $query): Builder
    {
        return $query->where('status', 'rotating');
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired() && !$this->isRevoked();
    }

    public function isRotating(): bool
    {
        return $this->status === 'rotating';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked' || $this->revoked_at !== null;
    }

    public function isDownloaded(): bool
    {
        return $this->downloaded_at !== null;
    }

    /**
     * 是否可以下载私钥（download_token 有效且未过期且未下载过）
     */
    public function canDownload(): bool
    {
        return $this->download_token !== null
            && $this->download_token_expires_at !== null
            && $this->download_token_expires_at->isFuture()
            && $this->downloaded_at === null;
    }
}
