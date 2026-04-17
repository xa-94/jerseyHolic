<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品同步规则表 — Merchant DB
 *
 * 定义主商品同步到各店铺的策略，包括目标站点、同步字段、价格策略等。
 * 支持自动同步和手动同步两种模式。
 */
return new class extends Migration
{
    protected $connection = 'merchant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('sync_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->comment('规则名称');
            $table->json('target_store_ids')->comment('目标站点ID列表');
            $table->json('excluded_store_ids')->nullable()->comment('排除站点');
            $table->json('sync_fields')->comment('要同步的字段列表');
            $table->string('price_strategy', 20)->default('fixed')->comment('fixed/multiplier/custom');
            $table->decimal('price_multiplier', 5, 2)->default(1.00)->comment('价格乘数');
            $table->boolean('auto_sync')->default(false)->comment('自动同步开关');
            $table->tinyInteger('status')->default(1)->comment('1=enabled, 0=disabled');
            $table->timestamps();

            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sync_rules');
    }
};
