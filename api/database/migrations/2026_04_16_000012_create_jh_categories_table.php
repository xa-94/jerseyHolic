<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 商品分类（嵌套集模型）
        Schema::create('jh_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父分类ID，0=顶级');
            $table->string('image', 255)->nullable()->comment('分类图片路径');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            // 嵌套集字段
            $table->integer('_lft')->default(0)->comment('嵌套集左值');
            $table->integer('_rgt')->default(0)->comment('嵌套集右值');
            $table->integer('depth')->default(0)->comment('层级深度');
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id', 'idx_categories_parent_id');
            $table->index(['_lft', '_rgt'], 'idx_categories_nested');
            $table->index('status', 'idx_categories_status');
        });

        // 分类多语言描述
        Schema::create('jh_category_descriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->comment('分类ID');
            $table->string('locale', 10)->comment('语言代码: en, de, fr, ar 等');
            $table->string('name', 255)->comment('分类名称');
            $table->text('description')->nullable()->comment('分类描述');
            $table->string('meta_title', 255)->default('')->comment('SEO 标题');
            $table->string('meta_description', 500)->default('')->comment('SEO 描述');
            $table->string('meta_keywords', 255)->default('')->comment('SEO 关键词');
            $table->string('slug', 255)->default('')->comment('URL slug');
            $table->timestamps();

            $table->unique(['category_id', 'locale'], 'udx_category_descriptions_cat_locale');
            $table->index('locale', 'idx_category_descriptions_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_category_descriptions');
        Schema::dropIfExists('jh_categories');
    }
};
