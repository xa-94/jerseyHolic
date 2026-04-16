<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 国家模型 — Tenant DB
 *
 * 对应表：jh_countries
 *
 * @property int    $id
 * @property string $name
 * @property string $iso_code_2
 * @property string $iso_code_3
 * @property string $address_format
 * @property int    $postcode_required
 * @property int    $status
 * @property int    $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Country extends TenantModel
{
    protected $table = 'jh_countries';

    protected $fillable = [
        'name', 'iso_code_2', 'iso_code_3', 'address_format',
        'postcode_required', 'status', 'sort_order',
    ];

    protected $casts = [
        'postcode_required' => 'boolean',
        'status'            => 'integer',
        'sort_order'        => 'integer',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'country_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
