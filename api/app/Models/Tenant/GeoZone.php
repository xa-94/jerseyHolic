<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 地理区域模型 — Tenant DB
 *
 * 对应表：jh_geo_zones
 *
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property int    $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class GeoZone extends TenantModel
{
    protected $table = 'jh_geo_zones';

    protected $fillable = [
        'name', 'description', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(GeoZoneRule::class, 'geo_zone_id');
    }
}
