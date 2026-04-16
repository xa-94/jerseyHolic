<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 运费规则 — Tenant DB
 *
 * 对应表：jh_shipping_rules
 */
class ShippingRule extends TenantModel
{
    protected $table = 'jh_shipping_rules';

    protected $fillable = [
        'shipping_zone_id', 'name', 'type', 'cost', 'rate',
        'free_threshold', 'min_weight', 'max_weight', 'sort_order', 'status',
    ];

    protected $casts = [
        'shipping_zone_id' => 'integer',
        'cost'             => 'decimal:2',
        'rate'             => 'decimal:4',
        'free_threshold'   => 'decimal:2',
        'min_weight'       => 'decimal:2',
        'max_weight'       => 'decimal:2',
        'sort_order'       => 'integer',
        'status'           => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
