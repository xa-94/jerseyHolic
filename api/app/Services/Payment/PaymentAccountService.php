<?php

namespace App\Services\Payment;

use App\Models\Central\PaymentAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * 支付账号服务
 *
 * 负责支付账号的 CRUD、状态切换及统计信息。
 * 配合 AccountLifecycleService 和 AccountHealthScoreService 实现全生命周期管理。
 */
class PaymentAccountService
{
    public function __construct(
        private readonly AccountLifecycleService    $lifecycleService,
        private readonly AccountHealthScoreService  $healthScoreService,
    ) {}

    /**
     * 获取账号列表（分页 + 筛选）
     *
     * @param  array $filters  可选 keys: pay_method, status, category_id, lifecycle_stage, per_page
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = PaymentAccount::query()->with(['group']);

        if (!empty($filters['pay_method'])) {
            $query->where('pay_method', $filters['pay_method']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['lifecycle_stage'])) {
            $query->lifecycle($filters['lifecycle_stage']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->latest()->paginate($perPage);
    }

    /**
     * 获取账号详情（含统计信息 + 健康度明细）
     *
     * @param  int $id
     * @return array
     */
    public function find(int $id): array
    {
        $account   = PaymentAccount::with(['group', 'ccGroup'])->findOrFail($id);
        $breakdown = $this->healthScoreService->getScoreBreakdown($account);

        return [
            'account'         => $account,
            'health_breakdown' => $breakdown,
        ];
    }

    /**
     * 创建支付账号
     *
     * 新账号默认进入 NEW 生命周期阶段，由 AccountLifecycleService 设置初始限额。
     *
     * @param  array $data
     * @return PaymentAccount
     */
    public function create(array $data): PaymentAccount
    {
        // 强制初始化生命周期为 NEW
        $data['lifecycle_stage'] = PaymentAccount::LIFECYCLE_NEW;
        $data['status']          = $data['status'] ?? 1;
        $data['permission']      = $data['permission'] ?? 1;

        $account = PaymentAccount::create($data);

        // 设置 NEW 阶段默认限额
        $this->lifecycleService->updateLimits($account);

        // 计算初始健康度
        $score = $this->healthScoreService->calculate($account);
        $account->update(['health_score' => $score]);

        return $account->fresh();
    }

    /**
     * 更新支付账号
     *
     * @param  int   $id
     * @param  array $data
     * @return PaymentAccount
     */
    public function update(int $id, array $data): PaymentAccount
    {
        $account = PaymentAccount::findOrFail($id);
        $account->update($data);

        return $account->fresh();
    }

    /**
     * 切换账号启用/禁用状态
     *
     * @param  int   $id
     * @param  array $data  可选 keys: status, permission
     * @return PaymentAccount
     */
    public function toggleStatus(int $id, array $data): PaymentAccount
    {
        $account = PaymentAccount::findOrFail($id);

        $updateData = [];
        if (isset($data['status'])) {
            $updateData['status'] = (int) $data['status'];
        }
        if (isset($data['permission'])) {
            $updateData['permission'] = (int) $data['permission'];
        }

        if (!empty($updateData)) {
            $account->update($updateData);

            Log::info('[PaymentAccountService] Account status toggled', [
                'account_id' => $id,
                'changes'    => $updateData,
            ]);
        }

        return $account->fresh();
    }

    /**
     * 删除支付账号（软删除）
     *
     * @param  int $id
     * @return void
     */
    public function delete(int $id): void
    {
        $account = PaymentAccount::findOrFail($id);
        $account->delete();

        Log::info('[PaymentAccountService] Account soft-deleted', ['account_id' => $id]);
    }
}
