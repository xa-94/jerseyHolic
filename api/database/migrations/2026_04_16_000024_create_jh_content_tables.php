<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 静态页面
        Schema::create('jh_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->comment('URL slug');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug', 'udx_pages_slug');
        });

        // 页面多语言内容
        Schema::create('jh_page_descriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id')->comment('页面ID');
            $table->string('locale', 10)->comment('语言代码');
            $table->string('title', 255)->comment('页面标题');
            $table->text('content')->nullable()->comment('页面内容(HTML)');
            $table->string('meta_title', 255)->default('')->comment('SEO 标题');
            $table->string('meta_description', 500)->default('')->comment('SEO 描述');
            $table->string('meta_keywords', 255)->default('')->comment('SEO 关键词');
            $table->timestamps();

            $table->unique(['page_id', 'locale'], 'udx_page_descriptions_page_locale');
        });

        // Banner 管理
        Schema::create('jh_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 128)->comment('Banner 标题');
            $table->string('image', 255)->comment('图片路径');
            $table->string('link', 512)->default('')->comment('点击链接');
            $table->string('position', 64)->default('homepage')->comment('展示位置: homepage/category/checkout');
            $table->string('locale', 10)->default('')->comment('语言限制，空=所有语言');
            $table->timestamp('start_at')->nullable()->comment('开始展示时间');
            $table->timestamp('end_at')->nullable()->comment('结束展示时间');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('position', 'idx_banners_position');
            $table->index('status', 'idx_banners_status');
        });

        // 操作日志
        Schema::create('jh_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operator_type', 32)->default('admin')->comment('操作者类型: admin/merchant/system');
            $table->unsignedBigInteger('operator_id')->default(0)->comment('操作者ID');
            $table->string('operator_name', 64)->default('')->comment('操作者名称');
            $table->string('module', 64)->comment('操作模块');
            $table->string('action', 64)->comment('操作动作: create/update/delete/login/export等');
            $table->string('description', 500)->default('')->comment('操作描述');
            $table->unsignedBigInteger('target_id')->default(0)->comment('操作对象ID');
            $table->string('target_type', 64)->default('')->comment('操作对象类型');
            $table->json('old_data')->nullable()->comment('变更前数据');
            $table->json('new_data')->nullable()->comment('变更后数据');
            $table->string('ip', 45)->default('')->comment('操作IP');
            $table->string('user_agent', 500)->default('')->comment('User-Agent');
            $table->timestamps();

            $table->index(['operator_type', 'operator_id'], 'idx_operation_logs_operator');
            $table->index('module', 'idx_operation_logs_module');
            $table->index('action', 'idx_operation_logs_action');
            $table->index('created_at', 'idx_operation_logs_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_operation_logs');
        Schema::dropIfExists('jh_banners');
        Schema::dropIfExists('jh_page_descriptions');
        Schema::dropIfExists('jh_pages');
    }
};
