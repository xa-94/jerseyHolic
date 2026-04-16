<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('地理区域名称，如 North America, EU');
            $table->string('description', 500)->default('')->comment('区域描述');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });

        Schema::create('geo_zone_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('geo_zone_id')->comment('地理区域ID');
            $table->unsignedBigInteger('country_id')->comment('国家ID');
            $table->unsignedBigInteger('zone_id')->default(0)->comment('地区ID，0=该国家全部地区');
            $table->timestamps();

            $table->index('geo_zone_id', 'idx_geo_zone_rules_geo_zone_id');
            $table->index(['country_id', 'zone_id'], 'idx_geo_zone_rules_country_zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_zone_rules');
        Schema::dropIfExists('geo_zones');
    }
};
