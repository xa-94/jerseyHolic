<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品 SKU/变体 — Tenant DB
 *
 * 对应表：jh_product_skus
 */
class ProductSku extends TenantModel
{
    protected $table = 'jh_product_skus';

    protected $fillable = [
        'product_id', 'sku', 'price', 'cost_price', 'quantity',
        'weight', 'image', 'option_values', 'status', 'sort_order',
    ];

    protected $casts = [
        'product_id'    => 'integer',
        'price'         => 'decimal:2',
        'cost_price'    => 'decimal:2',
        'quantity'      => 'integer',
        'weight'        => 'decimal:2',
        'option_values' => 'array',
        'status'        => 'integer',
        'sort_order'    => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
