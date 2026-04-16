<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jh_languages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('语言名称，如 English');
            $table->string('code', 10)->comment('语言代码，如 en, de, fr, ar');
            $table->string('locale', 20)->comment('完整 locale，如 en_US, pt_BR');
            $table->string('image', 255)->nullable()->comment('语言国旗图标路径');
            $table->string('directory', 64)->nullable()->comment('语言文件目录名');
            $table->string('direction', 5)->default('ltr')->comment('文字方向: ltr=左到右, rtl=右到左');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认语言: 0=否, 1=是');
            $table->timestamps();

            $table->unique('code', 'udx_languages_code');
            $table->index('status', 'idx_languages_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_languages');
    }
};
