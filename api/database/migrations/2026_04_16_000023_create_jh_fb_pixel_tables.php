<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facebook Pixel 配置
        Schema::create('jh_fb_pixel_configs', function (Blueprint $table) {
            $table->id();
            $table->string('pixel_id', 64)->comment('Facebook Pixel ID');
            $table->text('access_token')->nullable()->comment('Conversions API Access Token');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->string('domain', 255)->default('')->comment('关联域名，空=全局');
            $table->string('language_code', 10)->default('')->comment('关联语言，空=全部');
            $table->string('test_event_code', 64)->default('')->comment('测试事件代码');
            $table->integer('priority')->default(0)->comment('优先级');
            $table->json('enabled_events')->nullable()->comment('启用的事件列表JSON');
            $table->timestamps();

            $table->index('pixel_id', 'idx_fb_pixel_configs_pixel_id');
            $table->index('domain', 'idx_fb_pixel_configs_domain');
            $table->index('status', 'idx_fb_pixel_configs_status');
        });

        // Facebook 事件名称配置
        Schema::create('jh_fb_event_names', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 64)->comment('事件标识: PageView/ViewContent/AddToCart/Purchase等');
            $table->string('display_name', 128)->comment('显示名称');
            $table->tinyInteger('is_standard')->default(1)->comment('是否标准事件: 0=自定义, 1=标准');
            $table->json('parameters_schema')->nullable()->comment('事件参数JSON Schema');
            $table->string('description', 255)->default('')->comment('事件描述');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->unique('event_name', 'udx_fb_event_names_event_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_fb_event_names');
        Schema::dropIfExists('jh_fb_pixel_configs');
    }
};
