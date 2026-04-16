<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 订单商品明细 — Tenant DB
 *
 * 对应表：jh_order_items
 */
class OrderItem extends TenantModel
{
    protected $table = 'jh_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'product_sku_id', 'sku',
        'name', 'safe_name', 'image', 'quantity',
        'price', 'total', 'weight', 'options',
    ];

    protected $casts = [
        'order_id'       => 'integer',
        'product_id'     => 'integer',
        'product_sku_id' => 'integer',
        'quantity'       => 'integer',
        'price'          => 'decimal:2',
        'total'          => 'decimal:2',
        'weight'         => 'decimal:2',
        'options'        => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productSku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }
}
