<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 物流供应商映射 — Tenant DB
 *
 * 对应表：jh_shipping_provider_mappings
 */
class ShippingProviderMapping extends TenantModel
{
    protected $table = 'jh_shipping_provider_mappings';

    protected $fillable = [
        'provider_id', 'internal_name', 'external_name', 'platform', 'country_code',
    ];

    protected $casts = [
        'provider_id' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'provider_id');
    }
}
