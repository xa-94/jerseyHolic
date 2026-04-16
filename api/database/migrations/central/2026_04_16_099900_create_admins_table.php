<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->comment('登录用户名');
            $table->string('email', 128)->nullable()->comment('邮箱');
            $table->string('password', 255)->comment('bcrypt 加密密码');
            $table->string('name', 64)->default('')->comment('显示名称');
            $table->string('avatar', 255)->nullable()->comment('头像路径');
            $table->string('phone', 32)->nullable()->comment('手机号');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->tinyInteger('is_super')->default(0)->comment('是否超级管理员');
            $table->integer('login_failures')->default(0)->comment('连续登录失败次数');
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('username', 'udx_admins_username');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
