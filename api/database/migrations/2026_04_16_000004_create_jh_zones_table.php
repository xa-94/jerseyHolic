<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jh_zones', function (Blueprint $table) {
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
        Schema::dropIfExists('jh_zones');
    }
};
