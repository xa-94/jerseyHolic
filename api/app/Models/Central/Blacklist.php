<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;

/**
 * 黑名单模型 — Central DB
 *
 * 对应表：jh_blacklists（central 库）
 * 全局黑名单，支持 email/ip/phone/paypal_account 等多维度。
 *
 * @property int    $id
 * @property string $type
 * @property string $value
 * @property string|null $reason
 * @property string $source
 * @property int|null $merchant_id
 * @property int|null $store_id
 * @property int    $status
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Blacklist extends CentralModel
{
    protected $table = 'jh_blacklists';

    protected $fillable = [
        'type',
        'value',
        'reason',
        'source',
        'merchant_id',
        'store_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'merchant_id' => 'integer',
        'store_id'    => 'integer',
        'status'      => 'integer',
        'expires_at'  => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 仅有效（启用且未过期）的黑名单条目
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * 按类型筛选
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
