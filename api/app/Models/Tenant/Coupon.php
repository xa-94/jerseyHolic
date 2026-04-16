<?php

namespace App\Models\Tenant;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 优惠券 — Tenant DB
 *
 * 对应表：jh_coupons
 */
class Coupon extends TenantModel
{
    protected $table = 'jh_coupons';

    protected $fillable = [
        'code', 'name', 'type', 'discount', 'minimum_amount',
        'uses_total', 'uses_customer', 'used_count',
        'start_at', 'end_at', 'status',
    ];

    protected $casts = [
        'type'           => CouponType::class,
        'discount'       => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'uses_total'     => 'integer',
        'uses_customer'  => 'integer',
        'used_count'     => 'integer',
        'start_at'       => 'datetime',
        'end_at'         => 'datetime',
        'status'         => 'integer',
    ];

    public function usage(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
    }

    public function isUsable(): bool
    {
        if ($this->uses_total > 0 && $this->used_count >= $this->uses_total) {
            return false;
        }
        return true;
    }
}
