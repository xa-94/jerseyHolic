<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品属性值 — Tenant DB
 *
 * 对应表：jh_product_attribute_values
 */
class ProductAttributeValue extends TenantModel
{
    protected $table = 'jh_product_attribute_values';

    protected $fillable = [
        'product_id', 'attribute_id', 'value', 'locale',
    ];

    protected $casts = [
        'product_id'   => 'integer',
        'attribute_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }
}
