<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_risk_scores', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained('merchants')->onDelete('cascade');
            $table->enum('risk_level', ['L1', 'L2', 'L3', 'L4'])->default('L1');
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->decimal('dispute_rate', 5, 4)->default(0);
            $table->decimal('refund_rate', 5, 4)->default(0);
            $table->decimal('chargeback_amount_30d', 12, 2)->default(0);
            $table->decimal('order_anomaly_score', 5, 2)->default(0);
            $table->decimal('velocity_score', 5, 2)->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_risk_scores');
    }
};
