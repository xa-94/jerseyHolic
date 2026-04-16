<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant Model 基类
 *
 * 所有归属 Tenant DB（店铺级业务数据）的 Model 继承此类。
 *
 * 注意：stancl/tenancy 的 DatabaseTenancyBootstrapper 在租户上下文激活后
 * 会自动将默认数据库连接切换为当前租户连接。因此 Tenant Model 不需要显式
 * 设置 $connection 属性。此处不设置 $connection 以避免与 stancl 自动切换冲突。
 *
 * 如需在 Central 上下文中查询 Tenant 数据（如数据迁移脚本），
 * 请使用 ->setConnection('tenant') 或在查询中指定连接。
 */
abstract class TenantModel extends Model
{
    // 不显式设置 $connection，由 stancl/tenancy 的 DatabaseTenancyBootstrapper 自动处理。
    // 租户上下文激活后，默认连接即为当前租户的数据库连接。
}
