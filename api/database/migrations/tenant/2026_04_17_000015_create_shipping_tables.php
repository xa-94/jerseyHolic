<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 物流供应商
        Schema::create('shipping_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('供应商名称');
            $table->string('code', 64)->comment('供应商代码');
            $table->string('tracking_url', 512)->default('')->comment('查询链接模板');
            $table->string('api_config', 512)->default('')->comment('API 配置');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique('code', 'udx_shipping_providers_code');
        });

        // 物流供应商映射(内部名→PayPal/AfterShip标准名)
        Schema::create('shipping_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id')->comment('物流供应商ID');
            $table->string('internal_name', 255)->comment('内部渠道名');
            $table->string('external_name', 128)->comment('外部标准名(PayPal/AfterShip)');
            $table->string('platform', 32)->default('paypal')->comment('目标平台: paypal/aftership');
            $table->string('country_code', 2)->default('')->comment('目的国代码，空=通用');
            $table->timestamps();

            $table->index('provider_id', 'idx_shipping_provider_mappings_provider');
            $table->index(['internal_name', 'platform'], 'idx_shipping_provider_mappings_name_plat');
        });

        // 运费区域
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('运费区域名称');
            $table->unsignedBigInteger('geo_zone_id')->default(0)->comment('关联地理区域ID');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('geo_zone_id', 'idx_shipping_zones_geo_zone_id');
        });

        // 运费规则
        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id')->default(0)->comment('运费区域ID');
            $table->string('name', 128)->comment('规则名称');
            $table->string('type', 32)->comment('计算方式: fixed/weight/item/free');
            $table->decimal('cost', 12, 2)->default(0.00)->comment('基础费用(USD)');
            $table->decimal('rate', 8, 4)->default(0.00)->comment('费率(按重量/件数)');
            $table->decimal('free_threshold', 12, 2)->default(0.00)->comment('免运费门槛(USD)，0=不免运');
            $table->decimal('min_weight', 8, 2)->default(0.00)->comment('最小重量(kg)');
            $table->decimal('max_weight', 8, 2)->default(99999.00)->comment('最大重量(kg)');
            $table->integer('sort_order')->default(0)->comment('排序优先级');
            $table->tinyInteger('status')->default(1)->comment('状态: 0=禁用, 1=启用');
            $table->timestamps();

            $table->index('shipping_zone_id', 'idx_shipping_rules_zone_id');
            $table->index('type', 'idx_shipping_rules_type');
        });

        // 发货单
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('shipment_no', 64)->comment('发货单号');
            $table->unsignedBigInteger('provider_id')->default(0)->comment('物流供应商ID');
            $table->string('tracking_number', 128)->default('')->comment('物流跟踪号');
            $table->string('carrier_name', 128)->default('')->comment('承运商名称');
            $table->string('carrier_name_mapped', 128)->default('')->comment('映射后的标准承运商名称');
            $table->tinyInteger('status')->default(0)->comment('状态: 0=待发货,1=处理中,2=已发货,3=运输中,4=已签收,5=发货失败,6=已退回');
            $table->decimal('weight', 8, 2)->default(0.00)->comment('总重量(kg)');
            $table->string('safe_product_name', 255)->default('')->comment('面单使用的安全商品名称');
            $table->tinyInteger('paypal_uploaded')->default(0)->comment('是否已上传PayPal卖家保护: 0=否, 1=是');
            $table->timestamp('shipped_at')->nullable()->comment('发货时间');
            $table->timestamp('delivered_at')->nullable()->comment('签收时间');
            $table->timestamps();

            $table->unique('shipment_no', 'udx_shipments_shipment_no');
            $table->index('order_id', 'idx_shipments_order_id');
            $table->index('tracking_number', 'idx_shipments_tracking_number');
            $table->index('status', 'idx_shipments_status');
        });

        // 物流轨迹
        Schema::create('shipment_tracks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id')->comment('发货单ID');
            $table->string('status', 64)->default('')->comment('轨迹状态');
            $table->string('description', 500)->default('')->comment('轨迹描述');
            $table->string('location', 255)->default('')->comment('轨迹位置');
            $table->timestamp('tracked_at')->nullable()->comment('轨迹时间');
            $table->string('source', 32)->default('api')->comment('数据来源: api/manual');
            $table->timestamps();

            $table->index('shipment_id', 'idx_shipment_tracks_shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracks');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_provider_mappings');
        Schema::dropIfExists('shipping_providers');
    }
};
