<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品同步日志 Model — Central DB
 *
 * 对应表：product_sync_logs（central 库，无 jh_ 前缀）
 * 记录每次商品同步操作的详细信息，用于监控和排障。
 *
 * @property int         $id
 * @property int         $merchant_id
 * @property int|null    $source_store_id       源站点 ID（null = 主商品库）
 * @property string      $target_store_id       目标站点 ID（UUID/string）
 * @property string      $sync_type             full|incremental
 * @property string      $trigger               manual|auto|scheduled
 * @property string      $status                pending|running|completed|failed|partial
 * @property int         $total_products        本次同步总商品数
 * @property int         $synced_products       成功同步商品数
 * @property int         $failed_products       同步失败商品数
 * @property array|null  $error_log             错误日志（JSON）
 * @property \Carbon\Carbon|null $started_at    同步开始时间
 * @property \Carbon\Carbon|null $completed_at  同步完成时间
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Merchant    $merchant
 * @property-read Store       $store
 */
class ProductSyncLog extends CentralModel
{
    protected $table = 'product_sync_logs';

    protected $fillable = [
        'merchant_id',
        'source_store_id',
        'target_store_id',
        'sync_type',
        'trigger',
        'status',
        'total_products',
        'synced_products',
        'failed_products',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'merchant_id'      => 'integer',
        'source_store_id'  => 'integer',
        'total_products'   => 'integer',
        'synced_products'  => 'integer',
        'failed_products'  => 'integer',
        'error_log'        => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属商户
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 目标站点
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'target_store_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 按目标站点筛选
     */
    public function scopeOfStore(Builder $query, string $storeId): Builder
    {
        return $query->where('target_store_id', $storeId);
    }

    /**
     * 按商户筛选
     */
    public function scopeOfMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * 仅成功（completed）
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * 仅失败（failed）
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * 最近 N 小时内的记录
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /* ----------------------------------------------------------------
     |  计算属性
     | ---------------------------------------------------------------- */

    /**
     * 同步耗时（毫秒），基于 started_at / completed_at 计算
     */
    public function getDurationMsAttribute(): ?int
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->completed_at);
    }
}
