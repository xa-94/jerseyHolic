<?php

namespace App\Models\Tenant;

/**
 * Facebook 事件名称配置 — Tenant DB
 *
 * 对应表：jh_fb_event_names
 */
class FbEventName extends TenantModel
{
    protected $table = 'jh_fb_event_names';

    protected $fillable = [
        'event_name', 'display_name', 'is_standard',
        'parameters_schema', 'description', 'status',
    ];

    protected $casts = [
        'is_standard'       => 'boolean',
        'parameters_schema' => 'array',
        'status'            => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeStandard($query)
    {
        return $query->where('is_standard', 1);
    }
}
