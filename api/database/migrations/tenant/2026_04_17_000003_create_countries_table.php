<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('国家名称');
            $table->string('iso_code_2', 2)->comment('ISO 3166-1 alpha-2 代码');
            $table->string('iso_code_3', 3)->comment('ISO 3166-1 alpha-3 代码');
            $table->string('address_format', 512)->default('')->comment('地址格式模板');
            $table->tinyInteger('postcode_required')->default(0)->comment('是否必填邮编: 0=否, 1=是');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique('iso_code_2', 'udx_countries_iso2');
            $table->index('status', 'idx_countries_status');
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->comment('所属国家ID');
            $table->string('name', 128)->comment('地区名称');
            $table->string('code', 32)->comment('地区代码');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('country_id', 'idx_zones_country_id');
            $table->index(['country_id', 'code'], 'idx_zones_country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
        Schema::dropIfExists('countries');
    }
};
