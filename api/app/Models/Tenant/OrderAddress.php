<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 订单地址 — Tenant DB
 *
 * 对应表：jh_order_addresses
 */
class OrderAddress extends TenantModel
{
    protected $table = 'jh_order_addresses';

    protected $fillable = [
        'order_id', 'type', 'firstname', 'lastname', 'company',
        'address_1', 'address_2', 'city', 'postcode',
        'country', 'country_code', 'zone', 'zone_code',
        'phone', 'email',
    ];

    protected $casts = [
        'order_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    public function scopeShipping($query)
    {
        return $query->where('type', 'shipping');
    }

    public function scopeBilling($query)
    {
        return $query->where('type', 'billing');
    }
}
