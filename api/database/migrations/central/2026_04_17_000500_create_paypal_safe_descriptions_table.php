<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayPal 安全描述映射表 — M3 新增
 *
 * 将商品分类映射为 PayPal 可接受的安全名称和描述，降低风控拦截风险。
 * store_id 为 null 表示全局规则，非 null 表示站点级规则。
 * 多条规则匹配时取 weight 最高的。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paypal_safe_descriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->unsignedBigInteger('store_id')->nullable()->comment('关联站点 ID，null=全局规则');
            $table->string('product_category', 64)->comment('商品分类标识（如 jerseys, accessories）');
            $table->string('safe_name', 128)->comment('安全名称（展示给 PayPal 的商品名）');
            $table->string('safe_description', 255)->comment('安全描述文本');
            $table->string('safe_category_code', 16)->default('')->comment('MCC 分类码');
            $table->integer('weight')->default(0)->comment('权重，多条匹配时取最高');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用, 1=启用');
            $table->timestamps();

            // 按站点+分类查询
            $table->index(['store_id', 'product_category'], 'idx_store_category');
            // 按状态筛选
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_safe_descriptions');
    }
};
