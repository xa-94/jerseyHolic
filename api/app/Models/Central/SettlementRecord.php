<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商户结算记录模型 — Central DB
 *
 * 对应表：jh_merchant_settlements（central 库）
 *
 * @property int    $id
 * @property int    $merchant_id
 * @property string $settlement_no
 * @property float  $total_amount
 * @property float  $commission_amount
 * @property float  $net_amount
 * @property int    $order_count
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 * @property int    $status
 * @property string|null $remark
 * @property \Carbon\Carbon|null $settled_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SettlementRecord extends CentralModel
{
    protected $table = 'jh_merchant_settlements';

    protected $fillable = [
        'merchant_id',
        'settlement_no',
        'total_amount',
        'commission_amount',
        'net_amount',
        'order_count',
        'period_start',
        'period_end',
        'status',
        'remark',
        'settled_at',
    ];

    protected $casts = [
        'merchant_id'       => 'integer',
        'total_amount'      => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount'        => 'decimal:2',
        'order_count'       => 'integer',
        'period_start'      => 'date',
        'period_end'        => 'date',
        'status'            => 'integer',
        'settled_at'        => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SettlementDetail::class, 'settlement_id');
    }
}
