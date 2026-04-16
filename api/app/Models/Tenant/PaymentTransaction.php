<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 支付交易流水 — Tenant DB
 *
 * 对应表：jh_payment_transactions
 */
class PaymentTransaction extends TenantModel
{
    protected $table = 'jh_payment_transactions';

    protected $fillable = [
        'payment_id', 'order_id', 'transaction_id', 'type',
        'amount', 'currency', 'status', 'raw_data',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'order_id'   => 'integer',
        'amount'     => 'decimal:2',
        'status'     => 'integer',
        'raw_data'   => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
