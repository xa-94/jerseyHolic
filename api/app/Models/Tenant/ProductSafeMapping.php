<?php

namespace App\Models\Tenant;

use App\Enums\MappingType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品安全名称映射 — Tenant DB
 *
 * 对应表：jh_product_safe_mapping
 */
class ProductSafeMapping extends TenantModel
{
    protected $table = 'jh_product_safe_mapping';

    protected $fillable = [
        'product_id', 'safe_product_id', 'safe_name', 'mapping_type', 'is_active',
    ];

    protected $casts = [
        'product_id'      => 'integer',
        'safe_product_id' => 'integer',
        'is_active'       => 'boolean',
        'mapping_type'    => MappingType::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function safeProduct(): BelongsTo
    {
        return $this->belongsTo(SafeProduct::class, 'safe_product_id');
    }

    /**
     * Resolve the effective safe name: custom safe_name → safe_product.name
     */
    public function getEffectiveSafeNameAttribute(): string
    {
        if (!empty($this->safe_name)) {
            return $this->safe_name;
        }
        return $this->safeProduct?->name ?? 'Sports Training Jersey';
    }
}
