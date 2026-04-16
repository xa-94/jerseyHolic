<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
            $table->string('name', 100)->comment('站点名称');
            $table->string('code', 50)->unique()->comment('站点编码');
            $table->string('domain', 255)->unique()->comment('站点域名');
            $table->string('database_name', 100)->comment('站点数据库名');
            $table->string('database_host', 255)->default('127.0.0.1');
            $table->integer('database_port')->default(3306);
            $table->string('database_username', 100);
            $table->text('database_password')->comment('加密存储');
            $table->enum('status', ['provisioning', 'active', 'maintenance', 'inactive'])->default('provisioning');
            $table->json('target_markets')->nullable()->comment('目标市场(国家列表)');
            $table->json('supported_languages')->nullable()->comment('支持语言列表');
            $table->json('supported_currencies')->nullable()->comment('支持货币列表');
            $table->string('default_language', 10)->default('en');
            $table->string('default_currency', 3)->default('USD');
            $table->json('product_categories')->nullable()->comment('经营品类');
            $table->json('payment_preferences')->nullable()->comment('支付方式偏好');
            $table->json('logistics_config')->nullable()->comment('物流配置');
            $table->json('theme_config')->nullable()->comment('主题配置');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('merchant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
