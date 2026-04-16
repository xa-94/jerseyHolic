<?php

namespace App\Services;

use App\Events\StoreDeprovisioned;
use App\Events\StoreProvisioned;
use App\Events\StoreProvisionFailed;
use App\Exceptions\StoreProvisioningException;
use App\Jobs\GenerateNginxConfigJob;
use App\Jobs\ProvisionSSLCertificateJob;
use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 站点自动创建/销毁服务
 *
 * 负责站点（Store / Tenant）的完整生命周期管理：
 * 1. 验证商户状态与配额
 * 2. 在 Central DB 事务中创建 Store + Domain 记录
 * 3. stancl/tenancy 自动创建 Tenant DB 并运行迁移
 * 4. 初始化 Tenant DB 默认数据
 * 5. 异步派发 Nginx 配置和 SSL 证书 Job
 * 6. 标记 Store 为 active
 *
 * 事务策略：Central DB 操作在事务中执行，stancl 在 TenantCreated 事件中
 * 同步创建 Tenant DB（非队列模式），如果失败事务回滚即可清理 Central 记录。
 */
class StoreProvisioningService
{
    /**
     * 商户等级 → 站点数量上限映射
     *
     * @var array<string, int|null>  null 表示不限
     */
    protected const STORE_QUOTA = [
        'starter'  => 2,
        'standard' => 5,
        'advanced' => 10,
        'vip'      => null, // 不限
    ];

    /* ================================================================
     |  公开方法
     | ================================================================ */

    /**
     * 创建新站点的完整流程
     *
     * @param Merchant $merchant 所属商户（必须 active）
     * @param array    $data     站点配置数据，包含：
     *   - store_name: string        站点名称
     *   - store_code: string        站点唯一编码（用于子域名等）
     *   - domain: string            绑定域名（如 shop1.jerseyholic.com）
     *   - target_markets: ?array    目标市场列表
     *   - supported_languages: ?array
     *   - supported_currencies: ?array
     *   - product_categories: ?array
     *   - payment_preferences: ?array
     *   - logistics_config: ?array
     *   - theme_config: ?array
     *
     * @return Store 创建完成的 Store 实例
     *
     * @throws StoreProvisioningException
     */
    public function provision(Merchant $merchant, array $data): Store
    {
        Log::info('[StoreProvisioning] Starting provision.', [
            'merchant_id' => $merchant->id,
            'store_name'  => $data['store_name'] ?? null,
            'domain'      => $data['domain'] ?? null,
        ]);

        // ---- 1. 验证商户状态 ----
        $this->ensureMerchantActive($merchant);

        // ---- 2. 验证配额 ----
        $this->ensureStoreQuota($merchant);

        // ---- 3. 验证域名可用 ----
        $domainName = $data['domain'] ?? null;
        if ($domainName) {
            $this->ensureDomainAvailable($domainName);
        }

        $store = null;

        try {
            // ---- 4-6. 在事务中创建 Store + Domain (Central DB) ----
            // stancl/tenancy 监听 TenantCreated 事件同步执行 CreateDatabase + MigrateDatabase
            $store = DB::connection('central')->transaction(function () use ($merchant, $data, $domainName) {

                // 4. 创建 Store 记录（触发 stancl TenantCreated → CreateDatabase + MigrateDatabase）
                $store = Store::create([
                    'merchant_id'          => $merchant->id,
                    'store_name'           => $data['store_name'],
                    'store_code'           => $data['store_code'],
                    'domain'               => $domainName,
                    'status'               => 0, // provisioning
                    'database_name'        => null, // 由 stancl 自动生成
                    'database_password'    => Str::random(32),
                    'target_markets'       => $data['target_markets'] ?? null,
                    'supported_languages'  => $data['supported_languages'] ?? null,
                    'supported_currencies' => $data['supported_currencies'] ?? null,
                    'product_categories'   => $data['product_categories'] ?? null,
                    'payment_preferences'  => $data['payment_preferences'] ?? null,
                    'logistics_config'     => $data['logistics_config'] ?? null,
                    'theme_config'         => $data['theme_config'] ?? null,
                ]);

                // 5. 回写 database_name（stancl 根据 tenancy.database.prefix + id 生成）
                $dbName = $this->generateDatabaseName($store);
                $store->update(['database_name' => $dbName]);

                // 6. 创建 Domain 记录
                if ($domainName) {
                    $store->domains()->create([
                        'domain'             => $domainName,
                        'certificate_status' => Domain::CERT_PENDING,
                    ]);
                }

                return $store;
            });

            // ---- 7. stancl/tenancy 已在事件中完成 CreateDatabase + MigrateDatabase ----
            //         参见 TenancyServiceProvider::events() 中 TenantCreated 的 JobPipeline

            // ---- 8. 初始化 Tenant DB 默认数据 ----
            $this->seedTenantDatabase($store);

            // ---- 9. 异步派发 Nginx 配置 Job ----
            GenerateNginxConfigJob::dispatch($store);

            // ---- 10. 异步派发 SSL 证书 Job ----
            $primaryDomain = $store->domains()->first();
            if ($primaryDomain) {
                ProvisionSSLCertificateJob::dispatch($primaryDomain);
            }

            // ---- 11. 更新 Store 状态为 active ----
            $store->update(['status' => 1]);

            // ---- 12. 触发成功事件 ----
            event(new StoreProvisioned($store));

            Log::info('[StoreProvisioning] Provision completed successfully.', [
                'store_id'    => $store->id,
                'store_name'  => $store->store_name,
                'merchant_id' => $merchant->id,
            ]);

            return $store->fresh();

        } catch (StoreProvisioningException $e) {
            // 业务异常直接抛出（已在上层处理）
            event(new StoreProvisionFailed($store, $e, [
                'merchant_id' => $merchant->id,
                'data'        => $data,
            ]));
            throw $e;

        } catch (\Throwable $e) {
            Log::error('[StoreProvisioning] Unexpected error during provision.', [
                'merchant_id' => $merchant->id,
                'store_id'    => $store?->id,
                'error'       => $e->getMessage(),
            ]);

            // 如果 Store 已创建但后续步骤失败，标记为 provisioning_failed
            if ($store && $store->exists) {
                $store->update(['status' => -1]); // -1 = provisioning_failed
            }

            event(new StoreProvisionFailed($store, $e, [
                'merchant_id' => $merchant->id,
                'data'        => $data,
            ]));

            throw new StoreProvisioningException(
                StoreProvisioningException::DB_CREATION_FAILED,
                "Store provisioning failed: {$e->getMessage()}",
                422,
                $e
            );
        }
    }

    /**
     * 删除站点（软删除 + 标记数据库待清理）
     *
     * 不立即删除 Tenant DB，保留 30 天后由定时任务清理。
     *
     * @param Store $store 要删除的站点
     *
     * @return bool
     *
     * @throws StoreProvisioningException
     */
    public function deprovision(Store $store): bool
    {
        Log::info('[StoreProvisioning] Starting deprovision.', [
            'store_id'   => $store->id,
            'store_name' => $store->store_name,
        ]);

        // 1. 检查是否有未完成订单（在 Tenant 上下文中查询）
        $hasPendingOrders = $this->checkPendingOrders($store);
        if ($hasPendingOrders) {
            throw StoreProvisioningException::hasPendingOrders($store->id);
        }

        // 2. 软删除 Store 记录
        $store->update(['status' => 0]); // 标记为 inactive
        $store->delete(); // soft-delete

        // 3. 标记所有 Domain 为 inactive（软清理，不删除记录）
        $store->domains()->update(['certificate_status' => 'inactive']);

        // 4. 不立即删除 Tenant DB（保留 30 天后由定时任务清理）
        //    stancl TenantDeleted 事件中配置了 DeleteDatabase，但我们用软删除
        //    所以不会触发 TenantDeleted 事件，数据库会保留。

        // 5. 异步移除 Nginx 配置
        // TODO: 创建 RemoveNginxConfigJob 后在此派发

        // 6. 触发事件
        event(new StoreDeprovisioned($store));

        Log::info('[StoreProvisioning] Deprovision completed.', [
            'store_id' => $store->id,
        ]);

        return true;
    }

    /**
     * 验证商户站点配额是否已满
     *
     * @param Merchant $merchant
     *
     * @return bool true = 仍有配额
     */
    public function validateStoreQuota(Merchant $merchant): bool
    {
        $level = $merchant->level ?? 'starter';
        $limit = self::STORE_QUOTA[$level] ?? self::STORE_QUOTA['starter'];

        // null 表示不限
        if ($limit === null) {
            return true;
        }

        $currentCount = $merchant->stores()->count();

        return $currentCount < $limit;
    }

    /* ================================================================
     |  受保护方法
     | ================================================================ */

    /**
     * 生成租户数据库名称
     *
     * 与 tenancy.php 中 database.prefix + id 一致：store_{id}
     */
    protected function generateDatabaseName(Store $store): string
    {
        $prefix = config('tenancy.database.prefix', 'store_');
        $suffix = config('tenancy.database.suffix', '');

        return $prefix . $store->getTenantKey() . $suffix;
    }

    /**
     * 在 Tenant 上下文中执行 TenantDatabaseSeeder
     */
    protected function seedTenantDatabase(Store $store): void
    {
        try {
            $store->run(function () {
                $seeder = new TenantDatabaseSeeder();
                $seeder->run();
            });

            Log::info('[StoreProvisioning] Tenant database seeded.', [
                'store_id' => $store->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[StoreProvisioning] Tenant database seeding failed (non-fatal).', [
                'store_id' => $store->id,
                'error'    => $e->getMessage(),
            ]);
            // Seeding 失败不阻塞整体流程，仅记录警告
        }
    }

    /**
     * 确保商户处于激活状态
     *
     * @throws StoreProvisioningException
     */
    protected function ensureMerchantActive(Merchant $merchant): void
    {
        if (!$merchant->isActive()) {
            throw StoreProvisioningException::merchantInactive($merchant->id);
        }
    }

    /**
     * 确保商户的站点配额未满
     *
     * @throws StoreProvisioningException
     */
    protected function ensureStoreQuota(Merchant $merchant): void
    {
        if (!$this->validateStoreQuota($merchant)) {
            $level = $merchant->level ?? 'starter';
            $limit = self::STORE_QUOTA[$level] ?? self::STORE_QUOTA['starter'];

            throw StoreProvisioningException::quotaExceeded(
                $merchant->id,
                $level,
                $limit
            );
        }
    }

    /**
     * 确保域名未被占用
     *
     * @throws StoreProvisioningException
     */
    protected function ensureDomainAvailable(string $domainName): void
    {
        $exists = Domain::where('domain', $domainName)->exists();
        if ($exists) {
            throw StoreProvisioningException::domainTaken($domainName);
        }
    }

    /**
     * 检查站点是否有未完成订单
     *
     * 在 Tenant 上下文中查询 orders 表。
     */
    protected function checkPendingOrders(Store $store): bool
    {
        try {
            return $store->run(function () {
                // 状态 0=pending, 1=processing — 这些算"未完成"
                return DB::table('orders')
                    ->whereIn('order_status_id', [0, 1])
                    ->exists();
            });
        } catch (\Throwable $e) {
            // 如果 orders 表不存在或查询失败，视为无未完成订单
            Log::warning('[StoreProvisioning] Could not check pending orders.', [
                'store_id' => $store->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }
}
