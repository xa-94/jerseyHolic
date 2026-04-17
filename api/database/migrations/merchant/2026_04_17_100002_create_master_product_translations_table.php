<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品多语言翻译表 — Merchant DB
 *
 * 存储主商品的多语言翻译信息，支持 16 种语言。
 * 通过 [master_product_id, locale] 唯一约束保证每个商品每种语言只有一条翻译。
 */
return new class extends Migration
{
    protected $connection = 'merchant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('master_product_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('master_product_id')->comment('关联 master_products.id');
            $table->string('locale', 10)->comment('语言代码，如 en, zh, de');
            $table->string('name', 255)->comment('翻译后商品名称');
            $table->text('description')->nullable()->comment('翻译后商品描述');
            $table->string('meta_title', 255)->nullable()->comment('SEO 标题');
            $table->text('meta_description')->nullable()->comment('SEO 描述');
            $table->timestamps();

            $table->unique(['master_product_id', 'locale'], 'uq_product_locale');
            $table->index('locale', 'idx_locale');

            $table->foreign('master_product_id', 'fk_translation_product')
                  ->references('id')
                  ->on('master_products')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('master_product_translations');
    }
};
