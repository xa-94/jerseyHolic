<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建敏感品牌表（M4-003 特货自动识别增强）
 *
 * 存储品牌黑名单数据，用于三级判定引擎的 Level 2 — 品牌匹配。
 * 不使用 jh_ 前缀，表名：sensitive_brands
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('sensitive_brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('brand_name', 255)->comment('品牌名称');
            $table->json('brand_aliases')->nullable()->comment('品牌别名列表 JSON');
            $table->unsignedBigInteger('category_l1_id')->nullable()->comment('关联一级品类 ID（null=所有品类）');
            $table->string('risk_level', 20)->default('high')->comment('风险等级: high/medium/low');
            $table->string('reason', 500)->nullable()->comment('标记原因');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();

            // 联合唯一索引：brand_name + category_l1_id
            $table->unique(['brand_name', 'category_l1_id'], 'uniq_brand_category');
            // 状态索引
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sensitive_brands');
    }
};
