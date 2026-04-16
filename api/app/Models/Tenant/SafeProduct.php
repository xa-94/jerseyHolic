<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 安全商品名称库 — Tenant DB
 *
 * 对应表：jh_safe_products
 */
class SafeProduct extends TenantModel
{
    protected $table = 'jh_safe_products';

    protected $fillable = [
        'name', 'description', 'category', 'is_default', 'sort_order', 'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'status'     => 'integer',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(ProductSafeMapping::class, 'safe_product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
