<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 订单费用明细 — Tenant DB
 *
 * 对应表：jh_order_totals
 */
class OrderTotal extends TenantModel
{
    protected $table = 'jh_order_totals';

    protected $fillable = [
        'order_id', 'code', 'title', 'value', 'sort_order',
    ];

    protected $casts = [
        'order_id'   => 'integer',
        'value'      => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
