<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 买家地址模型 — Tenant DB
 *
 * 对应表：jh_customer_addresses
 *
 * @property int    $id
 * @property int    $customer_id
 * @property string $firstname
 * @property string $lastname
 * @property string $company
 * @property string $address_1
 * @property string $address_2
 * @property string $city
 * @property string $postcode
 * @property int    $country_id
 * @property string $country_name
 * @property int    $zone_id
 * @property string $zone_name
 * @property string $phone
 * @property int    $is_default
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CustomerAddress extends TenantModel
{
    protected $table = 'jh_customer_addresses';

    protected $fillable = [
        'customer_id', 'firstname', 'lastname', 'company',
        'address_1', 'address_2', 'city', 'postcode',
        'country_id', 'country_name', 'zone_id', 'zone_name',
        'phone', 'is_default',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'country_id'  => 'integer',
        'zone_id'     => 'integer',
        'is_default'  => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}
