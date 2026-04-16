<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 争议记录 — Tenant DB
 *
 * 对应表：jh_disputes
 */
class Dispute extends TenantModel
{
    protected $table = 'jh_disputes';

    protected $fillable = [
        'order_id', 'dispute_id', 'reason', 'dispute_type',
        'amount', 'currency', 'status', 'outcome',
        'messages', 'evidence', 'seller_response', 'resolved_at',
    ];

    protected $casts = [
        'order_id'    => 'integer',
        'amount'      => 'decimal:2',
        'status'      => 'integer',
        'messages'    => 'array',
        'evidence'    => 'array',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [1, 2]);
    }
}
