<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 运费区域 — Tenant DB
 *
 * 对应表：jh_shipping_zones
 */
class ShippingZone extends TenantModel
{
    protected $table = 'jh_shipping_zones';

    protected $fillable = [
        'name', 'geo_zone_id', 'status',
    ];

    protected $casts = [
        'geo_zone_id' => 'integer',
        'status'      => 'integer',
    ];

    public function geoZone(): BelongsTo
    {
        return $this->belongsTo(GeoZone::class, 'geo_zone_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ShippingRule::class, 'shipping_zone_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
