<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 品类体系 — L1 一级品类表（Central DB）
     *
     * 存储运动品类顶层分类（如 Jerseys、Footwear 等），
     * name 字段为 JSON 格式，支持 16 种语言。
     */
    public function up(): void
    {
        Schema::connection('central')->create('product_categories_l1', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique()->comment('品类编码，如 jerseys, footwear');
            $table->json('name')->comment('多语言名称 JSON，如 {"en":"Jerseys","zh":"球衣"}');
            $table->string('icon', 255)->nullable()->comment('品类图标 URL');
            $table->boolean('is_sensitive')->default(true)->comment('是否为敏感品类');
            $table->decimal('sensitive_ratio', 5, 2)->default(100.00)->comment('敏感占比百分比');
            $table->integer('sort_order')->default(0)->comment('排序权重，升序');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('product_categories_l1');
    }
};
