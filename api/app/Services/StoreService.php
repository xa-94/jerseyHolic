<?php

namespace App\Services;

use App\Models\Central\Domain;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 站点管理服务 — Central DB
 *
 * 封装 StoreProvisioningService，提供站点 CRUD、配置管理、域名管理等业务逻辑。
 * 所有操作均在 central 连接上执行。
 */
class StoreService
{
    public function __construct(
        private readonly StoreProvisioningService $provisioningService
    ) {}

    /* ================================================================
     |  站点 CRUD
     | ================================================================ */

    /**
     * 创建站点（委托 StoreProvisioningService::provision）
     *
     * @param Merchant $merchant 所属商户
     * @param array    $data     站点配置数据
     *
     * @return Store
     */
    public function createStore(Merchant $merchant, array $data): Store
    {
        return $this->provisioningService->provision($merchant, $data);
    }

    /**
     * 获取站点详情（含关联：merchant, domains, paymentAccounts）
     *
     * @param int $id
     *
     * @return Store
     */
    public function getStore(int $id): Store
    {
        return Store::on('central')
            ->with(['merchant', 'domains', 'paymentAccounts'])
            ->findOrFail($id);
    }

    /**
     * 更新站点基本信息
     *
     * @param int   $id
     * @param array $data
     *
     * @return Store
     */
    public function updateStore(int $id, array $data): Store
    {
        $store = Store::on('central')->findOrFail($id);

        $allowedFields = [
            'store_name',
            'target_markets',
            'supported_languages',
            'supported_currencies',
            'product_categories',
            'payment_preferences',
            'logistics_config',
            'theme_config',
        ];

        $store->update(array_intersect_key($data, array_flip($allowedFields)));

        return $store->fresh(['merchant', 'domains', 'paymentAccounts']);
    }

    /**
     * 站点列表（分页，支持按 merchant_id / status 筛选）
     *
     * @param array $filters  支持：merchant_id, status, per_page
     *
     * @return LengthAwarePaginator
     */
    public function listStores(array $filters): LengthAwarePaginator
    {
        $query = Store::on('central')->with(['merchant', 'domains']);

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $perPage = (int)($filters['per_page'] ?? 15);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * 获取商户名下所有站点（不分页）
     *
     * @param int $merchantId
     *
     * @return Collection
     */
    public function listStoresByMerchant(int $merchantId): Collection
    {
        return Store::on('central')
            ->with(['domains'])
            ->where('merchant_id', $merchantId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * 更新站点状态
     *
     * @param int    $id
     * @param string $status  active | maintenance | inactive
     *
     * @return Store
     */
    public function updateStatus(int $id, string $status): Store
    {
        $statusMap = [
            'active'      => 1,
            'maintenance' => 2,
            'inactive'    => 0,
        ];

        $store = Store::on('central')->findOrFail($id);
        $store->update(['status' => $statusMap[$status] ?? 0]);

        return $store->fresh();
    }

    /**
     * 删除站点（委托 StoreProvisioningService::deprovision）
     *
     * @param int $id
     *
     * @return bool
     */
    public function deleteStore(int $id): bool
    {
        $store = Store::on('central')->findOrFail($id);

        return $this->provisioningService->deprovision($store);
    }

    /* ================================================================
     |  配置管理
     | ================================================================ */

    /**
     * 更新产品分类配置（product_categories JSON）
     *
     * @param int   $id
     * @param array $categories
     *
     * @return Store
     */
    public function updateCategories(int $id, array $categories): Store
    {
        $store = Store::on('central')->findOrFail($id);
        $store->update(['product_categories' => $categories]);

        return $store->fresh();
    }

    /**
     * 更新目标市场配置（target_markets JSON）
     *
     * @param int   $id
     * @param array $markets
     *
     * @return Store
     */
    public function updateMarkets(int $id, array $markets): Store
    {
        $store = Store::on('central')->findOrFail($id);
        $store->update(['target_markets' => $markets]);

        return $store->fresh();
    }

    /**
     * 更新支持语言配置（supported_languages JSON）
     *
     * @param int   $id
     * @param array $languages
     *
     * @return Store
     */
    public function updateLanguages(int $id, array $languages): Store
    {
        $store = Store::on('central')->findOrFail($id);
        $store->update(['supported_languages' => $languages]);

        return $store->fresh();
    }

    /**
     * 更新支持货币配置（supported_currencies JSON）
     *
     * @param int   $id
     * @param array $currencies
     *
     * @return Store
     */
    public function updateCurrencies(int $id, array $currencies): Store
    {
        $store = Store::on('central')->findOrFail($id);
        $store->update(['supported_currencies' => $currencies]);

        return $store->fresh();
    }

    /**
     * 更新关联支付账号（同步 store_payment_accounts 中间表）
     *
     * @param int   $id
     * @param array $accountIds  支付账号 ID 列表
     *
     * @return Store
     */
    public function updatePaymentAccounts(int $id, array $accountIds): Store
    {
        $store = Store::on('central')->findOrFail($id);

        // sync 方法自动处理新增 / 删除
        $store->paymentAccounts()->sync($accountIds);

        return $store->fresh(['paymentAccounts']);
    }

    /**
     * 更新物流配置（logistics_config JSON）
     *
     * @param int   $id
     * @param array $config
     *
     * @return Store
     */
    public function updateLogistics(int $id, array $config): Store
    {
        $store = Store::on('central')->findOrFail($id);
        $store->update(['logistics_config' => $config]);

        return $store->fresh();
    }

    /**
     * 更新主题配置（theme_config JSON）
     *
     * @param int   $id
     * @param array $config
     *
     * @return Store
     */
    public function updateTheme(int $id, array $config): Store
    {
        $store = Store::on('central')->findOrFail($id);
        $store->update(['theme_config' => $config]);

        return $store->fresh();
    }

    /* ================================================================
     |  域名管理
     | ================================================================ */

    /**
     * 为站点添加域名
     *
     * @param int    $storeId
     * @param string $domain
     *
     * @return Domain
     */
    public function addDomain(int $storeId, string $domain): Domain
    {
        $store = Store::on('central')->findOrFail($storeId);

        return $store->domains()->create([
            'domain'             => $domain,
            'store_id'           => $storeId,
            'certificate_status' => Domain::CERT_PENDING,
        ]);
    }

    /**
     * 移除站点的某个域名
     *
     * @param int $storeId
     * @param int $domainId
     *
     * @return bool
     */
    public function removeDomain(int $storeId, int $domainId): bool
    {
        $domain = Domain::on('central')
            ->where('store_id', $storeId)
            ->findOrFail($domainId);

        return (bool)$domain->delete();
    }
}
