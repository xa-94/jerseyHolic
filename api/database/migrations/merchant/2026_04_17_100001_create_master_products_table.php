<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商户主商品表 — Merchant DB
 *
 * 每个商户独立数据库中的主商品表，用于统一管理商户所有商品的"原始数据"。
 * 商品信息可通过 SyncRule 同步到各个 Store（Tenant DB）的 products 表。
 *
 * 注意：此迁移运行在 merchant 连接上（jerseyholic_merchant_{id}），无 jh_ 前缀。
 */
return new class extends Migration
{
    protected $connection = 'merchant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('master_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku', 100)->unique()->comment('SKU编码');
            $table->string('name', 255)->comment('默认名称（英文）');
            $table->text('description')->nullable()->comment('默认描述');
            $table->unsignedBigInteger('category_l1_id')->nullable()->comment('Central DB L1品类ID');
            $table->unsignedBigInteger('category_l2_id')->nullable()->comment('Central DB L2品类ID');
            $table->boolean('is_sensitive')->default(false)->comment('是否特货（SensitiveGoodsService判定缓存）');
            $table->decimal('base_price', 10, 2)->comment('基础价格');
            $table->string('currency', 3)->default('USD')->comment('币种');
            $table->json('images')->nullable()->comment('图片URL数组');
            $table->json('attributes')->nullable()->comment('商品属性（颜色、尺码等）');
            $table->json('variants')->nullable()->comment('变体信息');
            $table->decimal('weight', 8, 2)->nullable()->comment('重量(g)');
            $table->json('dimensions')->nullable()->comment('尺寸 {length, width, height}');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive, 2=draft');
            $table->string('sync_status', 20)->default('pending')->comment('pending/syncing/synced/failed');
            $table->timestamp('last_synced_at')->nullable()->comment('最后同步时间');
            $table->timestamps();

            $table->index('category_l1_id', 'idx_category_l1');
            $table->index('category_l2_id', 'idx_category_l2');
            $table->index('status', 'idx_status');
            $table->index('sync_status', 'idx_sync_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('master_products');
    }
};
