<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Exceptions\BusinessException;
use App\Models\Central\Merchant;
use App\Models\Central\Store;
use App\Models\Merchant\SyncRule;
use App\Services\MerchantDatabaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * 同步规则 CRUD 服务
 *
 * 封装商户同步规则（Merchant DB sync_rules 表）的增删改查。
 * 所有数据库操作通过 MerchantDatabaseService::run() 切入商户库上下文。
 */
class SyncRuleService
{
    public function __construct(
        protected readonly MerchantDatabaseService $merchantDb,
    ) {}

    /* ================================================================
     |  查询
     | ================================================================ */

    /**
     * 同步规则列表（分页 + 筛选）
     *
     * @param  Merchant $merchant  当前商户
     * @param  array    $filters   可选筛选项：
     *   - name           string  规则名称模糊搜索
     *   - price_strategy string  价格策略：fixed/multiplier/custom
     *   - auto_sync      bool    是否自动同步
     *   - status         int     状态（0=disabled, 1=enabled）
     *   - per_page       int     每页条数（默认 15，最大 100）
     * @return LengthAwarePaginator
     */
    public function list(Merchant $merchant, array $filters = []): LengthAwarePaginator
    {
        return $this->merchantDb->run($merchant, function () use ($filters) {
            $query = SyncRule::query()->orderByDesc('id');

            // 规则名称模糊搜索
            if (!empty($filters['name'])) {
                $query->where('name', 'LIKE', "%{$filters['name']}%");
            }

            // 价格策略筛选
            if (!empty($filters['price_strategy'])) {
                $query->where('price_strategy', $filters['price_strategy']);
            }

            // 自动同步筛选
            if (isset($filters['auto_sync']) && $filters['auto_sync'] !== '') {
                $query->where('auto_sync', (bool) $filters['auto_sync']);
            }

            // 状态筛选
            if (isset($filters['status']) && $filters['status'] !== '') {
                $query->where('status', (int) $filters['status']);
            }

            $perPage = min((int) ($filters['per_page'] ?? 15), 100);

            return $query->paginate($perPage);
        });
    }

    /**
     * 同步规则详情
     *
     * @param  Merchant $merchant
     * @param  int      $ruleId
     * @return SyncRule
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show(Merchant $merchant, int $ruleId): SyncRule
    {
        return $this->merchantDb->run($merchant, function () use ($ruleId) {
            return SyncRule::findOrFail($ruleId);
        });
    }

    /* ================================================================
     |  创建 / 更新
     | ================================================================ */

    /**
     * 创建同步规则
     *
     * 验证 target_store_ids 中的站点归属当前商户。
     *
     * @param  Merchant $merchant
     * @param  array    $data
     * @return SyncRule
     *
     * @throws BusinessException  站点不属于当前商户
     */
    public function create(Merchant $merchant, array $data): SyncRule
    {
        // 验证目标站点归属
        $this->validateStoreOwnership($merchant, $data['target_store_ids'] ?? []);

        return $this->merchantDb->run($merchant, function () use ($data, $merchant) {
            $rule = SyncRule::create($data);

            Log::info('[SyncRule] Created.', [
                'merchant_id' => $merchant->id,
                'rule_id'     => $rule->id,
                'name'        => $rule->name,
            ]);

            return $rule;
        });
    }

    /**
     * 更新同步规则
     *
     * @param  int      $ruleId
     * @param  Merchant $merchant
     * @param  array    $data
     * @return SyncRule
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws BusinessException  站点不属于当前商户
     */
    public function update(int $ruleId, Merchant $merchant, array $data): SyncRule
    {
        // 若更新了目标站点，验证归属
        if (isset($data['target_store_ids'])) {
            $this->validateStoreOwnership($merchant, $data['target_store_ids']);
        }

        return $this->merchantDb->run($merchant, function () use ($ruleId, $data, $merchant) {
            $rule = SyncRule::findOrFail($ruleId);
            $rule->update($data);

            Log::info('[SyncRule] Updated.', [
                'merchant_id' => $merchant->id,
                'rule_id'     => $rule->id,
            ]);

            return $rule->fresh();
        });
    }

    /* ================================================================
     |  删除
     | ================================================================ */

    /**
     * 删除同步规则
     *
     * @param  int      $ruleId
     * @param  Merchant $merchant
     * @return bool
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(int $ruleId, Merchant $merchant): bool
    {
        return $this->merchantDb->run($merchant, function () use ($ruleId, $merchant) {
            $rule = SyncRule::findOrFail($ruleId);
            $rule->delete();

            Log::info('[SyncRule] Deleted.', [
                'merchant_id' => $merchant->id,
                'rule_id'     => $ruleId,
            ]);

            return true;
        });
    }

    /* ================================================================
     |  业务查询
     | ================================================================ */

    /**
     * 获取适用于指定站点的同步规则列表
     *
     * 从所有启用的规则中筛选 target_store_ids 包含该 storeId
     * 且 excluded_store_ids 不包含该 storeId 的规则。
     *
     * @param  Merchant $merchant
     * @param  int      $storeId
     * @return \Illuminate\Database\Eloquent\Collection<SyncRule>
     */
    public function getRulesForStore(Merchant $merchant, int $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->merchantDb->run($merchant, function () use ($storeId) {
            return SyncRule::enabled()
                ->whereJsonContains('target_store_ids', $storeId)
                ->get()
                ->filter(fn (SyncRule $rule) => $rule->appliesToStore($storeId))
                ->values();
        });
    }

    /* ================================================================
     |  辅助方法
     | ================================================================ */

    /**
     * 验证目标站点 ID 是否全部归属当前商户
     *
     * @param  Merchant $merchant
     * @param  array    $storeIds
     * @return void
     *
     * @throws BusinessException  存在不属于当前商户的站点
     */
    protected function validateStoreOwnership(Merchant $merchant, array $storeIds): void
    {
        if (empty($storeIds)) {
            return;
        }

        $validCount = Store::where('merchant_id', $merchant->id)
            ->whereIn('id', $storeIds)
            ->count();

        if ($validCount !== count($storeIds)) {
            throw new BusinessException(40301, '部分目标站点不属于当前商户');
        }
    }
}
