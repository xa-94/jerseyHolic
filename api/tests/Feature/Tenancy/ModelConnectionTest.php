<?php

namespace Tests\Feature\Tenancy;

use App\Models\Central\CentralModel;
use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Models\Tenant\Product;
use App\Models\Tenant\Order;
use App\Models\Tenant\Customer;
use App\Models\Tenant\TenantModel;
use Tests\TenancyTestCase;

/**
 * Model 连接测试
 *
 * 验证 Central Model 和 Tenant Model 基类的数据库连接行为：
 * - Central Model 始终使用 'central' 连接
 * - Tenant Model 不设固定 connection，由 stancl/tenancy 动态切换
 */
class ModelConnectionTest extends TenancyTestCase
{
    /* ================================================================
     |  Central Model 连接测试
     | ================================================================ */

    /** @test 测试 CentralModel 基类强制使用 central 连接 */
    public function test_central_model_base_class_uses_central_connection(): void
    {
        // 通过反射检查 CentralModel 的 $connection 属性
        $reflection = new \ReflectionClass(CentralModel::class);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);

        // 创建一个匿名子类来读取默认值
        $instance = new class extends CentralModel {
            protected $table = 'test';
        };

        $this->assertEquals('central', $instance->getConnectionName());
    }

    /** @test 测试 Merchant Model 使用 central 连接 */
    public function test_merchant_model_uses_central_connection(): void
    {
        $merchant = new Merchant();
        $this->assertEquals('central', $merchant->getConnectionName());
    }

    /** @test 测试 Store Model 使用 central 连接（通过 stancl CentralConnection） */
    public function test_store_model_uses_central_connection(): void
    {
        $store = new Store();
        // Store 继承 stancl Tenant，内含 CentralConnection trait
        $this->assertEquals('central', $store->getConnectionName());
    }

    /** @test 测试 Domain Model 使用 central 连接 */
    public function test_domain_model_uses_central_connection(): void
    {
        $domain = new Domain();
        $this->assertEquals('central', $domain->getConnectionName());
    }

    /* ================================================================
     |  Tenant Model 连接测试
     | ================================================================ */

    /** @test 测试 TenantModel 基类不设固定 connection */
    public function test_tenant_model_base_class_has_no_fixed_connection(): void
    {
        $reflection = new \ReflectionClass(TenantModel::class);

        // TenantModel 不应声明 $connection 属性（或保持 null）
        if ($reflection->hasProperty('connection')) {
            $property = $reflection->getProperty('connection');
            $property->setAccessible(true);

            $instance = new class extends TenantModel {
                protected $table = 'test';
            };

            // connection 应为 null 或默认值（不是 'central'）
            $connectionValue = $property->getValue($instance);
            $this->assertNotEquals('central', $connectionValue);
        } else {
            // 没有 $connection 属性，符合预期
            $this->assertTrue(true);
        }
    }

    /** @test 测试 Tenant Model（如 Product）在租户上下文中使用租户连接 */
    public function test_tenant_model_uses_tenant_connection_in_tenant_context(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant);

        $this->runInTenantContext($store, function () {
            $product = new Product();
            $connectionName = $product->getConnectionName();

            // 在租户上下文中，默认连接已被切换，不应是 'central'
            $this->assertNotEquals('central', $connectionName);
        });
    }

    /** @test 测试 Central Model 在租户上下文中仍然使用 central 连接 */
    public function test_central_model_keeps_central_connection_in_tenant_context(): void
    {
        $merchant = $this->createMerchant();
        $store = $this->createStore($merchant);

        $this->runInTenantContext($store, function () {
            // 即使在租户上下文中，Central Model 也应使用 central
            $merchantModel = new Merchant();
            $this->assertEquals('central', $merchantModel->getConnectionName());

            $storeModel = new Store();
            $this->assertEquals('central', $storeModel->getConnectionName());

            $domainModel = new Domain();
            $this->assertEquals('central', $domainModel->getConnectionName());
        });
    }

    /** @test 测试多个 Tenant Model 子类都不使用固定 central 连接 */
    public function test_various_tenant_models_do_not_use_central_connection(): void
    {
        $tenantModels = [
            Product::class,
            Order::class,
            Customer::class,
        ];

        foreach ($tenantModels as $modelClass) {
            $instance = new $modelClass();
            $this->assertNotEquals(
                'central',
                $instance->getConnectionName(),
                "{$modelClass} 不应使用 'central' 连接"
            );
        }
    }
}
