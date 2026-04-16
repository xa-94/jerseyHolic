<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jh_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('买家ID');
            $table->string('firstname', 64)->comment('名');
            $table->string('lastname', 64)->comment('姓');
            $table->string('company', 128)->default('')->comment('公司名');
            $table->string('address_1', 255)->comment('地址行1');
            $table->string('address_2', 255)->default('')->comment('地址行2');
            $table->string('city', 128)->comment('城市');
            $table->string('postcode', 16)->default('')->comment('邮编');
            $table->unsignedBigInteger('country_id')->comment('国家ID');
            $table->string('country_name', 128)->default('')->comment('国家名称冗余');
            $table->unsignedBigInteger('zone_id')->default(0)->comment('地区/州ID');
            $table->string('zone_name', 128)->default('')->comment('地区名称冗余');
            $table->string('phone', 32)->default('')->comment('电话');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认地址: 0=否, 1=是');
            $table->timestamps();

            $table->index('customer_id', 'idx_customer_addresses_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jh_customer_addresses');
    }
};
