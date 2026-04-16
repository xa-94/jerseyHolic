<?php

namespace App\Models\Tenant;

/**
 * 系统配置模型 — Tenant DB
 *
 * 对应表：jh_settings
 *
 * @property int    $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property int    $is_serialized
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Setting extends TenantModel
{
    protected $table = 'jh_settings';

    protected $fillable = [
        'group', 'key', 'value', 'type', 'is_serialized',
    ];

    protected $casts = [
        'is_serialized' => 'boolean',
    ];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Get typed value.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }
}
