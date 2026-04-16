<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 商品 SKU/变体
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->string('sku', 64)->comment('SKU 编码');
            $table->decimal('price', 12, 2)->default(0.00)->comment('SKU 价格(USD)，0 时使用商品主价格');
            $table->decimal('cost_price', 12, 2)->default(0.00)->comment('SKU 成本价(USD)');
            $table->integer('quantity')->default(0)->comment('SKU 库存');
            $table->decimal('weight', 8, 2)->default(0.00)->comment('SKU 重量(kg)');
            $table->string('image', 255)->nullable()->comment('SKU 图片');
            $table->json('option_values')->nullable()->comment('选项组合JSON，如 {"color":"Red","size":"XL"}');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique('sku', 'udx_product_skus_sku');
            $table->index('product_id', 'idx_product_skus_product_id');
            $table->index(['product_id', 'status'], 'idx_product_skus_product_status');
        });

        // 商品图片
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->string('image', 255)->comment('图片路径');
            $table->tinyInteger('is_main')->default(0)->comment('是否主图: 0=否, 1=是');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('product_id', 'idx_product_images_product_id');
        });

        // 商品属性
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('属性名称，如 Color, Size');
            $table->string('type', 32)->default('select')->comment('属性类型: select/input/radio/checkbox/color');
            $table->tinyInteger('is_required')->default(0)->comment('是否必选: 0=否, 1=是');
            $table->tinyInteger('is_filterable')->default(1)->comment('是否可筛选: 0=否, 1=是');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();
        });

        // 商品属性值
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->unsignedBigInteger('attribute_id')->comment('属性ID');
            $table->string('value', 255)->comment('属性值');
            $table->string('locale', 10)->default('en')->comment('语言代码');
            $table->timestamps();

            $table->index(['product_id', 'attribute_id'], 'idx_product_attribute_values_prod_attr');
            $table->index('attribute_id', 'idx_product_attribute_values_attr_id');
        });

        // 商品-分类关联（多对多）
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->unsignedBigInteger('category_id')->comment('分类ID');
            $table->tinyInteger('is_primary')->default(0)->comment('是否主分类: 0=否, 1=是');
            $table->timestamps();

            $table->unique(['product_id', 'category_id'], 'udx_product_categories_prod_cat');
            $table->index('category_id', 'idx_product_categories_cat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_skus');
    }
};
