<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 物流供应商 — Tenant DB
 *
 * 对应表：jh_shipping_providers
 */
class ShippingProvider extends TenantModel
{
    protected $table = 'jh_shipping_providers';

    protected $fillable = [
        'name', 'code', 'tracking_url', 'api_config', 'status', 'sort_order',
    ];

    protected $casts = [
        'status'     => 'integer',
        'sort_order' => 'integer',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(ShippingProviderMapping::class, 'provider_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
