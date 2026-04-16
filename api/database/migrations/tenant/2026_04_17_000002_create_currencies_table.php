<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 64)->comment('货币名称，如 US Dollar');
            $table->string('code', 10)->comment('ISO 4217 货币代码，如 USD, EUR');
            $table->string('symbol_left', 16)->default('')->comment('左侧符号，如 $');
            $table->string('symbol_right', 16)->default('')->comment('右侧符号');
            $table->integer('decimal_places')->default(2)->comment('小数位数');
            $table->decimal('exchange_rate', 15, 8)->default(1.00000000)->comment('对 USD 的汇率');
            $table->tinyInteger('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认货币: 0=否, 1=是');
            $table->timestamp('rate_updated_at')->nullable()->comment('汇率最后更新时间');
            $table->timestamps();

            $table->unique('code', 'udx_currencies_code');
            $table->index('status', 'idx_currencies_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
