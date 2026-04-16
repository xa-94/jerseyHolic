<?php

namespace App\Models\Tenant;

/**
 * Facebook Pixel 配置 — Tenant DB
 *
 * 对应表：jh_fb_pixel_configs
 */
class FbPixelConfig extends TenantModel
{
    protected $table = 'jh_fb_pixel_configs';

    protected $fillable = [
        'pixel_id', 'access_token', 'status', 'domain', 'language_code',
        'test_event_code', 'priority', 'enabled_events',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'status'         => 'integer',
        'priority'       => 'integer',
        'enabled_events' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain)->orWhere('domain', '');
    }
}
