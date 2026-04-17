<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 jh_products 表添加同步溯源字段
 *
 * sync_source_id 用于幂等同步：存储来源 MasterProduct 的 ID，
 * 同一 MasterProduct 重复同步时只更新不新建。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jh_products', function (Blueprint $table) {
            $table->unsignedBigInteger('sync_source_id')
                ->nullable()
                ->after('merchant_id')
                ->comment('来源 MasterProduct ID（用于同步幂等）');

            $table->timestamp('synced_at')
                ->nullable()
                ->after('sync_source_id')
                ->comment('最后同步时间');

            $table->unique(['merchant_id', 'sync_source_id'], 'udx_products_merchant_sync_source');
        });
    }

    public function down(): void
    {
        Schema::table('jh_products', function (Blueprint $table) {
            $table->dropUnique('udx_products_merchant_sync_source');
            $table->dropColumn(['sync_source_id', 'synced_at']);
        });
    }
};
