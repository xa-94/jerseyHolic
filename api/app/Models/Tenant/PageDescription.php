<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 页面多语言内容 — Tenant DB
 *
 * 对应表：jh_page_descriptions
 */
class PageDescription extends TenantModel
{
    protected $table = 'jh_page_descriptions';

    protected $fillable = [
        'page_id', 'locale', 'title', 'content',
        'meta_title', 'meta_description', 'meta_keywords',
    ];

    protected $casts = [
        'page_id' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}
