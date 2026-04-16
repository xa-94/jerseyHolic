<?php

namespace App\Models\Tenant;

/**
 * 语言模型 — Tenant DB
 *
 * 对应表：jh_languages
 *
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property string $locale
 * @property string|null $image
 * @property string|null $directory
 * @property string $direction
 * @property int    $sort_order
 * @property int    $status
 * @property int    $is_default
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Language extends TenantModel
{
    protected $table = 'jh_languages';

    protected $fillable = [
        'name', 'code', 'locale', 'image', 'directory',
        'direction', 'sort_order', 'status', 'is_default',
    ];

    protected $casts = [
        'sort_order'  => 'integer',
        'status'      => 'integer',
        'is_default'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeRtl($query)
    {
        return $query->where('direction', 'rtl');
    }

    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }
}
