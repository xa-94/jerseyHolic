<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('客户组名称');
            $table->string('description', 255)->default('')->comment('组描述');
            $table->decimal('discount_rate', 5, 2)->default(0.00)->comment('折扣率(%)');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认组: 0=否, 1=是');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_group_id')->default(1)->comment('客户组ID');
            $table->string('firstname', 64)->comment('名');
            $table->string('lastname', 64)->comment('姓');
            $table->string('email', 128)->comment('邮箱');
            $table->string('password', 255)->comment('bcrypt 加密密码');
            $table->string('phone', 32)->nullable()->comment('手机号');
            $table->tinyInteger('newsletter')->default(0)->comment('是否订阅邮件: 0=否, 1=是');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->string('ip', 45)->nullable()->comment('注册IP');
            $table->string('language_code', 10)->default('en')->comment('偏好语言');
            $table->string('currency_code', 10)->default('USD')->comment('偏好货币');
            $table->integer('login_failures')->default(0)->comment('连续登录失败次数');
            $table->timestamp('locked_until')->nullable()->comment('锁定截止时间');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('email', 'udx_customers_email');
            $table->index('customer_group_id', 'idx_customers_group_id');
            $table->index('status', 'idx_customers_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
