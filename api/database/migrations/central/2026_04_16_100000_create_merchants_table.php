<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->string('merchant_name', 100)->comment('商户名称');
            $table->string('email', 150)->unique()->comment('登录邮箱');
            $table->string('password', 255)->comment('密码');
            $table->string('contact_name', 100)->comment('联系人');
            $table->string('phone', 30)->nullable()->comment('联系电话');
            $table->string('merchant_id', 50)->nullable()->comment('外部商户编号');
            $table->string('api_key', 255)->nullable()->comment('API Key');
            $table->string('api_secret', 255)->nullable()->comment('API Secret');
            $table->enum('level', ['starter', 'standard', 'advanced', 'vip'])->default('starter')->comment('商户等级');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=待审核, 1=启用, 2=禁用');
            $table->integer('login_failures')->default(0)->comment('连续登录失败次数');
            $table->timestamp('locked_until')->nullable()->comment('锁定截止时间');
            $table->timestamp('fund_frozen_until')->nullable()->comment('资金冻结截止时间');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->comment('最后登录IP');
            $table->timestamp('approved_at')->nullable()->comment('审核通过时间');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
