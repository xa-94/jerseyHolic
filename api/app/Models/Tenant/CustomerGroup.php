<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 客户组模型 — Tenant DB
 *
 * 对应表：jh_customer_groups
 *
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property float  $discount_rate
 * @property int    $sort_order
 * @property int    $is_default
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CustomerGroup extends TenantModel
{
    protected $table = 'jh_customer_groups';

    protected $fillable = [
        'name', 'description', 'discount_rate', 'sort_order', 'is_default',
    ];

    protected $casts = [
        'discount_rate' => 'decimal:2',
        'sort_order'    => 'integer',
        'is_default'    => 'boolean',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'customer_group_id');
    }
}
