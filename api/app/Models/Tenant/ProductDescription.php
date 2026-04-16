<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品多语言描述 — Tenant DB
 *
 * 对应表：jh_product_descriptions
 */
class ProductDescription extends TenantModel
{
    protected $table = 'jh_product_descriptions';

    protected $fillable = [
        'product_id', 'locale', 'name', 'description', 'short_description',
        'meta_title', 'meta_description', 'meta_keywords', 'slug', 'tag',
    ];

    protected $casts = [
        'product_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
