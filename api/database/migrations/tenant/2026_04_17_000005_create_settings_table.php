<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64)->default('general')->comment('配置分组: general/shop/payment/shipping/email等');
            $table->string('key', 128)->comment('配置键名');
            $table->text('value')->nullable()->comment('配置值');
            $table->string('type', 32)->default('string')->comment('值类型: string/integer/boolean/json/text');
            $table->tinyInteger('is_serialized')->default(0)->comment('是否序列化: 0=否, 1=是');
            $table->timestamps();

            $table->unique(['group', 'key'], 'udx_settings_group_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
