<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品图片 — Tenant DB
 *
 * 对应表：jh_product_images
 */
class ProductImage extends TenantModel
{
    protected $table = 'jh_product_images';

    protected $fillable = [
        'product_id', 'image', 'is_main', 'sort_order',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'is_main'    => 'integer',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
