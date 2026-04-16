<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jh_merchants', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_name', 128)->comment('商户名称');
            $table->string('email', 128)->comment('商户邮箱');
            $table->string('password', 255)->comment('bcrypt 加密密码');
            $table->string('contact_name', 64)->default('')->comment('联系人姓名');
            $table->string('phone', 32)->nullable()->comment('联系电话');
            $table->string('merchant_id', 64)->nullable()->comment('商户标识ID（用于API）');
            $table->string('api_key', 255)->nullable()->comment('HMAC-SHA256 API 密钥');
            $table->string('api_secret', 255)->nullable()->comment('HMAC-SHA256 API 密钥Secret');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->integer('login_failures')->default(0)->comment('连续登录失败次数');
            $table->timestamp('locked_until')->nullable()->comment('锁定截止时间');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->comment('最后登录IP');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('email', 'udx_merchants_email');
            $table->unique('merchant_id', 'udx_merchants_merchant_id');
            $table->index('status', 'idx_merchants_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_merchants');
    }
};
