<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 订单扩展信息 — Tenant DB
 *
 * 对应表：jh_order_ext
 */
class OrderExt extends TenantModel
{
    protected $table = 'jh_order_ext';

    protected $fillable = [
        'order_id', 'success_url', 'cancel_url', 'notify_url', 'extra_data',
    ];

    protected $casts = [
        'order_id'   => 'integer',
        'extra_data' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
