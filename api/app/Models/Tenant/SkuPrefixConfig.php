<?php

namespace App\Models\Tenant;

use App\Enums\SkuCategory;

/**
 * SKU 前缀配置 — Tenant DB
 *
 * 对应表：jh_sku_prefix_configs
 */
class SkuPrefixConfig extends TenantModel
{
    protected $table = 'jh_sku_prefix_configs';

    protected $fillable = [
        'prefix', 'classification', 'default_safe_name', 'needs_mapping', 'description',
    ];

    protected $casts = [
        'needs_mapping' => 'boolean',
    ];

    /**
     * Get SkuCategory enum from this config.
     */
    public function toSkuCategory(): SkuCategory
    {
        return SkuCategory::tryFrom($this->prefix) ?? SkuCategory::UNKNOWN;
    }
}
