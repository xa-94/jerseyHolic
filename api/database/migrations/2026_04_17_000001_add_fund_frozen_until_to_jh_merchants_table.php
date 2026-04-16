<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 为 jh_merchants 表添加资金冻结截止时间字段，
     * 用于商户封禁时的资金冻结机制（冻结 180 天）。
     */
    public function up(): void
    {
        Schema::table('jh_merchants', function (Blueprint $table) {
            $table->timestamp('fund_frozen_until')
                  ->nullable()
                  ->after('locked_until')
                  ->comment('资金冻结截止时间（封禁时设置为 now + 180天）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jh_merchants', function (Blueprint $table) {
            $table->dropColumn('fund_frozen_until');
        });
    }
};
