<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 支付账号收款日志 — Central DB
 *
 * 对应表：jh_payment_account_logs（central 库）
 *
 * @property int    $id
 * @property int    $account_id
 * @property int    $order_id
 * @property float  $amount
 * @property string $currency
 * @property float  $original_amount
 * @property string $action
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaymentAccountLog extends CentralModel
{
    protected $table = 'payment_account_logs';

    protected $fillable = [
        'account_id', 'order_id', 'amount', 'currency', 'original_amount', 'action',
    ];

    protected $casts = [
        'account_id'      => 'integer',
        'order_id'        => 'integer',
        'amount'          => 'decimal:2',
        'original_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'account_id');
    }
}
