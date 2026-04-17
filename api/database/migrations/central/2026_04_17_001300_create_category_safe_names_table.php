<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 品类级安全映射名称表（Central DB / M4-002）
     *
     * 存储仿牌商品→普货的多语言安全名称映射。
     * 支持按 L1/L2 品类、SKU 前缀、站点级覆盖等维度管理，
     * 并以加权随机方式选取，防止固定模式被支付平台识别。
     */
    public function up(): void
    {
        Schema::connection('central')->create('category_safe_names', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('category_l1_id')->nullable()->comment('关联 L1 品类');
            $table->unsignedBigInteger('category_l2_id')->nullable()->comment('关联 L2 品类（精确到 L2 级）');
            $table->string('sku_prefix', 50)->nullable()->comment('SKU 前缀匹配，如 hic、WPZ');
            $table->unsignedBigInteger('store_id')->nullable()->comment('站点级覆盖（null=全局）');

            // 16 语言安全名称
            $table->string('safe_name_en', 255)->comment('英文安全名称');
            $table->string('safe_name_zh', 255)->nullable()->comment('中文安全名称');
            $table->string('safe_name_de', 255)->nullable()->comment('德文安全名称');
            $table->string('safe_name_fr', 255)->nullable()->comment('法文安全名称');
            $table->string('safe_name_es', 255)->nullable()->comment('西班牙文安全名称');
            $table->string('safe_name_it', 255)->nullable()->comment('意大利文安全名称');
            $table->string('safe_name_pt', 255)->nullable()->comment('葡萄牙文安全名称');
            $table->string('safe_name_nl', 255)->nullable()->comment('荷兰文安全名称');
            $table->string('safe_name_pl', 255)->nullable()->comment('波兰文安全名称');
            $table->string('safe_name_sv', 255)->nullable()->comment('瑞典文安全名称');
            $table->string('safe_name_da', 255)->nullable()->comment('丹麦文安全名称');
            $table->string('safe_name_ar', 255)->nullable()->comment('阿拉伯文安全名称');
            $table->string('safe_name_tr', 255)->nullable()->comment('土耳其文安全名称');
            $table->string('safe_name_el', 255)->nullable()->comment('希腊文安全名称');
            $table->string('safe_name_ja', 255)->nullable()->comment('日文安全名称');
            $table->string('safe_name_ko', 255)->nullable()->comment('韩文安全名称');

            $table->integer('weight')->default(10)->comment('加权随机选取权重');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();

            // 索引
            $table->index(['category_l1_id', 'category_l2_id'], 'idx_category');
            $table->index('sku_prefix', 'idx_sku_prefix');
            $table->index('store_id', 'idx_store_id');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('category_safe_names');
    }
};
