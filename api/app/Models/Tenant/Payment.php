<?php

namespace App\Models\Tenant;

use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 支付记录 — Tenant DB
 *
 * 对应表：jh_payments
 */
class Payment extends TenantModel
{
    protected $table = 'jh_payments';

    protected $fillable = [
        'order_id', 'payment_no', 'account_id', 'channel',
        'amount', 'original_amount', 'currency',
        'external_order_id', 'external_transaction_id',
        'status', 'payer_email', 'payer_id',
        'raw_response', 'failure_reason',
    ];

    protected $casts = [
        'order_id'        => 'integer',
        'account_id'      => 'integer',
        'channel'         => PaymentChannel::class,
        'amount'          => 'decimal:2',
        'original_amount' => 'decimal:2',
        'status'          => 'integer',
        'raw_response'    => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Central\PaymentAccount::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 1);
    }
}
