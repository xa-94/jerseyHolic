<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 佣金规则表 — M3 新增
 *
 * 定义平台对商户的抽佣费率，支持三级优先级：
 * 1. 站点级（store_id IS NOT NULL）— 最高优先级
 * 2. 商户级（merchant_id IS NOT NULL AND store_id IS NULL）
 * 3. 全局（merchant_id IS NULL AND store_id IS NULL）— 兜底
 *
 * 实际费率 = max(min_rate, min(max_rate, base_rate - volume_discount - loyalty_discount))
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('merchant_id')->nullable()->comment('商户 ID，null=全局规则');
            $table->unsignedBigInteger('store_id')->nullable()->comment('站点 ID，null=商户级规则');
            $table->string('rule_type', 32)->comment('规则类型标识');
            $table->string('tier_name', 64)->comment('阶梯名称（如 Default Tier, VIP Tier）');
            $table->decimal('base_rate', 5, 2)->comment('基础费率(%)');
            $table->decimal('volume_discount', 5, 2)->default(0.00)->comment('量级折扣(%)');
            $table->decimal('loyalty_discount', 5, 2)->default(0.00)->comment('忠诚度折扣(%)');
            $table->decimal('min_rate', 5, 2)->default(0.00)->comment('最低费率(%)');
            $table->decimal('max_rate', 5, 2)->default(100.00)->comment('最高费率(%)');
            $table->tinyInteger('enabled')->default(1)->comment('是否启用：0=禁用, 1=启用');
            $table->timestamps();

            // 按商户查询
            $table->index('merchant_id', 'idx_merchant_id');
            // 按站点查询
            $table->index('store_id', 'idx_store_id');
            // 按规则类型筛选
            $table->index('rule_type', 'idx_rule_type');
            // 按状态筛选
            $table->index('enabled', 'idx_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
