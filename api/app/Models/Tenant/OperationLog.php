<?php

namespace App\Models\Tenant;

/**
 * 操作日志 — Tenant DB
 *
 * 对应表：jh_operation_logs
 */
class OperationLog extends TenantModel
{
    protected $table = 'jh_operation_logs';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'operator_type', 'operator_id', 'operator_name',
        'module', 'action', 'description',
        'target_id', 'target_type',
        'old_data', 'new_data',
        'ip', 'user_agent',
    ];

    protected $casts = [
        'operator_id' => 'integer',
        'target_id'   => 'integer',
        'old_data'    => 'array',
        'new_data'    => 'array',
    ];

    public function scopeModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByOperator($query, string $type, int $id)
    {
        return $query->where('operator_type', $type)->where('operator_id', $id);
    }
}
