<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 品类体系 — L2 二级品类表（Central DB）
     *
     * 隶属于 L1 一级品类，存储细分品类（如 Soccer Jerseys、Basketball Jerseys 等），
     * name 字段为 JSON 格式，支持 16 种语言。
     */
    public function up(): void
    {
        Schema::connection('central')->create('product_categories_l2', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('l1_id')->comment('所属 L1 品类 ID');
            $table->string('code', 50)->comment('品类编码，如 soccer_jerseys');
            $table->json('name')->comment('多语言名称 JSON');
            $table->boolean('is_sensitive')->default(true)->comment('是否为敏感品类');
            $table->integer('sort_order')->default(0)->comment('排序权重，升序');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();

            $table->unique(['l1_id', 'code'], 'uk_l1_code');
            $table->foreign('l1_id')
                  ->references('id')
                  ->on('product_categories_l1')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('product_categories_l2');
    }
};
