<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 订单状态变更历史 — Tenant DB
 *
 * 对应表：jh_order_histories
 */
class OrderHistory extends TenantModel
{
    protected $table = 'jh_order_histories';

    protected $fillable = [
        'order_id', 'status_type', 'old_status', 'new_status',
        'comment', 'operator', 'notify_customer',
    ];

    protected $casts = [
        'order_id'        => 'integer',
        'old_status'      => 'integer',
        'new_status'      => 'integer',
        'notify_customer' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
