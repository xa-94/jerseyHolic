<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 地区/州模型 — Tenant DB
 *
 * 对应表：jh_zones
 *
 * @property int    $id
 * @property int    $country_id
 * @property string $name
 * @property string $code
 * @property int    $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Zone extends TenantModel
{
    protected $table = 'jh_zones';

    protected $fillable = [
        'country_id', 'name', 'code', 'status',
    ];

    protected $casts = [
        'country_id' => 'integer',
        'status'     => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
