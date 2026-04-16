<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 安全商品名称库
        Schema::create('safe_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('安全商品名称，如 Sports Jersey');
            $table->string('description', 500)->default('')->comment('安全描述');
            $table->string('category', 32)->default('general')->comment('适用分类: hic/DIY/NBL/general');
            $table->tinyInteger('is_default')->default(0)->comment('是否兜底默认: 0=否, 1=是');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('category', 'idx_safe_products_category');
            $table->index('status', 'idx_safe_products_status');
            $table->index('is_default', 'idx_safe_products_is_default');
        });

        // 商品与安全名称精确映射关系
        Schema::create('product_safe_mapping', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->unsignedBigInteger('safe_product_id')->nullable()->comment('安全名称库ID，null时使用safe_name字段');
            $table->string('safe_name', 255)->default('')->comment('自定义安全名称（优先于safe_product_id）');
            $table->string('mapping_type', 16)->default('exact')->comment('映射类型: exact=精确, prefix=前缀, default=兜底');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用: 0=否, 1=是');
            $table->timestamps();

            $table->unique('product_id', 'udx_product_safe_mapping_product_id');
            $table->index('safe_product_id', 'idx_product_safe_mapping_safe_id');
            $table->index('mapping_type', 'idx_product_safe_mapping_type');
        });

        // SKU 前缀配置
        Schema::create('sku_prefix_configs', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 8)->comment('SKU 前缀: hic/WPZ/DIY/NBL');
            $table->string('classification', 32)->comment('分类名: counterfeit/genuine/custom/other');
            $table->string('default_safe_name', 255)->comment('默认安全名称');
            $table->tinyInteger('needs_mapping')->default(1)->comment('是否需要映射: 0=否, 1=是');
            $table->string('description', 255)->default('')->comment('前缀描述');
            $table->timestamps();

            $table->unique('prefix', 'udx_sku_prefix_configs_prefix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_prefix_configs');
        Schema::dropIfExists('product_safe_mapping');
        Schema::dropIfExists('safe_products');
    }
};
