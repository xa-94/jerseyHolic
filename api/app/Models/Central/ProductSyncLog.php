<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品同步日志模型 — Central DB
 *
 * 对应表：jh_product_sync_logs（central 库）
 * 记录商户向平台/店铺同步商品的操作日志。
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property int|null $store_id
 * @property string $action
 * @property int    $total_count
 * @property int    $success_count
 * @property int    $fail_count
 * @property array|null  $error_details
 * @property int    $status
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProductSyncLog extends CentralModel
{
    protected $table = 'jh_product_sync_logs';

    protected $fillable = [
        'merchant_id',
        'store_id',
        'action',
        'total_count',
        'success_count',
        'fail_count',
        'error_details',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'merchant_id'   => 'integer',
        'store_id'      => 'integer',
        'total_count'   => 'integer',
        'success_count' => 'integer',
        'fail_count'    => 'integer',
        'error_details' => 'array',
        'status'        => 'integer',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
