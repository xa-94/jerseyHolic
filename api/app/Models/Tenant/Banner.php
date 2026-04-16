<?php

namespace App\Models\Tenant;

/**
 * Banner 管理 — Tenant DB
 *
 * 对应表：jh_banners
 */
class Banner extends TenantModel
{
    protected $table = 'jh_banners';

    protected $fillable = [
        'title', 'image', 'link', 'position', 'locale',
        'start_at', 'end_at', 'sort_order', 'status',
    ];

    protected $casts = [
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
        'sort_order' => 'integer',
        'status'     => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopePosition($query, string $position)
    {
        return $query->where('position', $position);
    }
}
