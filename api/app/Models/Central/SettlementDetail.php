<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 结算明细模型 — Central DB
 *
 * 对应表：jh_settlement_details（central 库）
 * 一条结算记录的逐店铺明细。
 *
 * @property int    $id
 * @property int    $settlement_id
 * @property int    $store_id
 * @property int    $order_count
 * @property float  $total_amount
 * @property float  $commission_amount
 * @property float  $net_amount
 * @property string $currency
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SettlementDetail extends CentralModel
{
    protected $table = 'settlement_details';

    protected $fillable = [
        'settlement_id',
        'store_id',
        'order_count',
        'total_amount',
        'commission_amount',
        'net_amount',
        'currency',
    ];

    protected $casts = [
        'settlement_id'     => 'integer',
        'store_id'          => 'integer',
        'order_count'       => 'integer',
        'total_amount'      => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount'        => 'decimal:2',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function settlementRecord(): BelongsTo
    {
        return $this->belongsTo(SettlementRecord::class, 'settlement_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
