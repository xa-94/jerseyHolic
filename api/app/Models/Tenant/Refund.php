<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 退款记录 — Tenant DB
 *
 * 对应表：jh_refunds
 */
class Refund extends TenantModel
{
    protected $table = 'jh_refunds';

    protected $fillable = [
        'order_id', 'payment_id', 'refund_no', 'external_refund_id',
        'amount', 'currency', 'reason', 'type', 'status',
        'operator', 'raw_response', 'refunded_at',
    ];

    protected $casts = [
        'order_id'     => 'integer',
        'payment_id'   => 'integer',
        'amount'       => 'decimal:2',
        'type'         => 'integer',
        'status'       => 'integer',
        'raw_response' => 'array',
        'refunded_at'  => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 0);
    }
}
