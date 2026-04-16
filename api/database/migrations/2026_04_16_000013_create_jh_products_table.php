<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 商品主表
        Schema::create('jh_products', function (Blueprint $table) {
            $table->id();
            $table->string('model', 64)->default('')->comment('商品型号');
            $table->string('sku', 64)->default('')->comment('主 SKU 编码');
            $table->string('sku_prefix', 8)->default('')->comment('SKU 前缀分类: hic/WPZ/DIY/NBL');
            $table->decimal('price', 12, 2)->default(0.00)->comment('基础售价(USD)');
            $table->decimal('cost_price', 12, 2)->default(0.00)->comment('成本价(USD)');
            $table->decimal('special_price', 12, 2)->nullable()->comment('促销价(USD)');
            $table->timestamp('special_start_at')->nullable()->comment('促销开始时间');
            $table->timestamp('special_end_at')->nullable()->comment('促销结束时间');
            $table->integer('quantity')->default(0)->comment('库存数量');
            $table->tinyInteger('stock_status')->default(1)->comment('库存状态: 0=缺货, 1=有货, 2=预售');
            $table->tinyInteger('subtract_stock')->default(1)->comment('是否扣减库存: 0=否, 1=是');
            $table->decimal('weight', 8, 2)->default(0.00)->comment('重量(kg)');
            $table->decimal('length', 8, 2)->default(0.00)->comment('长度(cm)');
            $table->decimal('width', 8, 2)->default(0.00)->comment('宽度(cm)');
            $table->decimal('height', 8, 2)->default(0.00)->comment('高度(cm)');
            $table->string('image', 255)->nullable()->comment('主图路径');
            $table->integer('minimum')->default(1)->comment('最小购买数量');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=下架, 1=上架, 2=草稿');
            $table->tinyInteger('is_featured')->default(0)->comment('是否推荐: 0=否, 1=是');
            $table->tinyInteger('requires_shipping')->default(1)->comment('是否需要配送: 0=否, 1=是');
            $table->unsignedBigInteger('merchant_id')->default(0)->comment('所属商户ID，0=平台自营');
            $table->integer('viewed')->default(0)->comment('浏览次数');
            $table->integer('sold')->default(0)->comment('销售数量');
            $table->string('upc', 16)->default('')->comment('UPC码');
            $table->string('ean', 16)->default('')->comment('EAN码');
            $table->string('isbn', 16)->default('')->comment('ISBN码');
            $table->string('mpn', 64)->default('')->comment('制造商零件编号');
            $table->timestamps();
            $table->softDeletes();

            $table->index('sku', 'idx_products_sku');
            $table->index('sku_prefix', 'idx_products_sku_prefix');
            $table->index('status', 'idx_products_status');
            $table->index('merchant_id', 'idx_products_merchant_id');
            $table->index(['status', 'sort_order'], 'idx_products_status_sort');
            $table->index('model', 'idx_products_model');
        });

        // 商品多语言描述
        Schema::create('jh_product_descriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->string('locale', 10)->comment('语言代码: en, de, fr, ar 等');
            $table->string('name', 255)->comment('商品名称');
            $table->text('description')->nullable()->comment('商品详细描述(HTML)');
            $table->string('short_description', 500)->default('')->comment('商品简短描述');
            $table->string('meta_title', 255)->default('')->comment('SEO 标题');
            $table->string('meta_description', 500)->default('')->comment('SEO 描述');
            $table->string('meta_keywords', 255)->default('')->comment('SEO 关键词');
            $table->string('slug', 255)->default('')->comment('URL slug');
            $table->string('tag', 255)->default('')->comment('标签(逗号分隔)');
            $table->timestamps();

            $table->unique(['product_id', 'locale'], 'udx_product_descriptions_product_locale');
            $table->index('locale', 'idx_product_descriptions_locale');
            $table->index('name', 'idx_product_descriptions_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_product_descriptions');
        Schema::dropIfExists('jh_products');
    }
};
