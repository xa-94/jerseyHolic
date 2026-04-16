<?php

namespace App\Models\Central;

/**
 * 资金流水日志模型 — Central DB
 *
 * 对应表：jh_fund_flow_logs（central 库）
 * 记录平台级资金流转（收款、退款、结算、提现等）。
 *
 * @property int    $id
 * @property string $flow_no
 * @property string $type
 * @property string $direction
 * @property int    $merchant_id
 * @property int|null $store_id
 * @property int|null $order_id
 * @property float  $amount
 * @property string $currency
 * @property float  $balance_after
 * @property string|null $reference_type
 * @property int|null    $reference_id
 * @property string|null $remark
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FundFlowLog extends CentralModel
{
    protected $table = 'fund_flow_logs';

    protected $fillable = [
        'flow_no',
        'type',
        'direction',
        'merchant_id',
        'store_id',
        'order_id',
        'amount',
        'currency',
        'balance_after',
        'reference_type',
        'reference_id',
        'remark',
    ];

    protected $casts = [
        'merchant_id'   => 'integer',
        'store_id'      => 'integer',
        'order_id'      => 'integer',
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'reference_id'  => 'integer',
    ];
}
