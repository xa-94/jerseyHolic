<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 地理区域规则模型 — Tenant DB
 *
 * 对应表：jh_geo_zone_rules
 *
 * @property int $id
 * @property int $geo_zone_id
 * @property int $country_id
 * @property int $zone_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class GeoZoneRule extends TenantModel
{
    protected $table = 'jh_geo_zone_rules';

    protected $fillable = [
        'geo_zone_id', 'country_id', 'zone_id',
    ];

    protected $casts = [
        'geo_zone_id' => 'integer',
        'country_id'  => 'integer',
        'zone_id'     => 'integer',
    ];

    public function geoZone(): BelongsTo
    {
        return $this->belongsTo(GeoZone::class, 'geo_zone_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
