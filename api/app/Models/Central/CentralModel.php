<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * Central Model 基类
 *
 * 所有归属 Central DB（平台级/商户级）的 Model 继承此类。
 * 强制使用 central 数据库连接，不受 stancl/tenancy 租户上下文切换影响。
 *
 * @property-read string $connection
 */
abstract class CentralModel extends Model
{
    /**
     * 强制使用 central 数据库连接。
     *
     * @var string
     */
    protected $connection = 'central';
}
