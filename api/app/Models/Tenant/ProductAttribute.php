<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商品属性 — Tenant DB
 *
 * 对应表：jh_product_attributes
 */
class ProductAttribute extends TenantModel
{
    protected $table = 'jh_product_attributes';

    protected $fillable = [
        'name', 'type', 'is_required', 'is_filterable', 'sort_order', 'status',
    ];

    protected $casts = [
        'is_required'   => 'integer',
        'is_filterable' => 'integer',
        'sort_order'    => 'integer',
        'status'        => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
    }
}
