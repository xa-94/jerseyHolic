<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 优惠券使用记录 — Tenant DB
 *
 * 对应表：jh_coupon_usage
 */
class CouponUsage extends TenantModel
{
    protected $table = 'jh_coupon_usage';

    protected $fillable = [
        'coupon_id', 'order_id', 'customer_id', 'discount_amount',
    ];

    protected $casts = [
        'coupon_id'       => 'integer',
        'order_id'        => 'integer',
        'customer_id'     => 'integer',
        'discount_amount' => 'decimal:2',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
