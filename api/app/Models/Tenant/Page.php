<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 静态页面 — Tenant DB
 *
 * 对应表：jh_pages
 */
class Page extends TenantModel
{
    use SoftDeletes;

    protected $table = 'jh_pages';

    protected $fillable = [
        'slug', 'status', 'sort_order',
    ];

    protected $casts = [
        'status'     => 'integer',
        'sort_order' => 'integer',
    ];

    public function descriptions(): HasMany
    {
        return $this->hasMany(PageDescription::class, 'page_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
