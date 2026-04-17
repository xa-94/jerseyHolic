<?php

declare(strict_types=1);

namespace App\Models\Merchant;

use Illuminate\Database\Eloquent\Model;

/**
 * Merchant Model 基类
 *
 * 所有归属 Merchant DB（商户级主商品、同步规则等）的 Model 继承此类。
 *
 * Merchant DB 命名：jerseyholic_merchant_{merchant_id}
 * 表无 jh_ 前缀（与 Central/Tenant 不同）。
 *
 * 连接策略：
 * - 默认使用 'merchant' 连接（database.php 中定义，database=null）
 * - 在业务代码中，由 MerchantDatabaseService::setConnection() 或中间件
 *   动态设置 config('database.connections.merchant.database') 为当前商户库名
 * - Model 内不硬编码数据库名，由运行时上下文决定
 */
abstract class MerchantModel extends Model
{
    /**
     * 使用 merchant 数据库连接。
     *
     * @var string
     */
    protected $connection = 'merchant';
}
