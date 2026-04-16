<?php

namespace App\Services\Payment;

use App\Models\Central\PaymentAccountGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * 支付账号分组服务
 *
 * 负责支付账号分组的 CRUD 操作。
 * 分组策略类型：VIP_EXCLUSIVE / STANDARD_SHARED / LITE_SHARED / BLACKLIST_ISOLATED
 */
class PaymentAccountGroupService
{
    /**
     * 获取分组列表（分页 + 筛选）
     *
     * @param  array $filters  可选 keys: type, group_type, status, per_page
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = PaymentAccountGroup::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['group_type'])) {
            $query->where('group_type', $filters['group_type']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        // 附加关联账号数量
        $query->withCount('paymentAccounts');

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->latest()->paginate($perPage);
    }

    /**
     * 获取分组详情（含关联账号）
     *
     * @param  int $id
     * @return PaymentAccountGroup
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(int $id): PaymentAccountGroup
    {
        return PaymentAccountGroup::with(['paymentAccounts'])->findOrFail($id);
    }

    /**
     * 创建分组
     *
     * @param  array $data
     * @return PaymentAccountGroup
     */
    public function create(array $data): PaymentAccountGroup
    {
        return PaymentAccountGroup::create([
            'name'               => $data['name'],
            'type'               => $data['type'],
            'group_type'         => $data['group_type'] ?? PaymentAccountGroup::GROUP_TYPE_STANDARD_SHARED,
            'description'        => $data['description'] ?? null,
            'is_blacklist_group' => $data['is_blacklist_group'] ?? 0,
            'status'             => $data['status'] ?? PaymentAccountGroup::STATUS_ENABLED,
        ]);
    }

    /**
     * 更新分组
     *
     * @param  int   $id
     * @param  array $data
     * @return PaymentAccountGroup
     */
    public function update(int $id, array $data): PaymentAccountGroup
    {
        $group = PaymentAccountGroup::findOrFail($id);
        $group->update($data);

        return $group->fresh();
    }

    /**
     * 删除分组（无关联账号才可删）
     *
     * @param  int $id
     * @return void
     * @throws ValidationException  分组下仍有关联账号时
     */
    public function delete(int $id): void
    {
        $group = PaymentAccountGroup::withCount('paymentAccounts')->findOrFail($id);

        if ($group->payment_accounts_count > 0) {
            throw ValidationException::withMessages([
                'id' => ['该分组下仍有关联支付账号，无法删除'],
            ]);
        }

        $group->delete();
    }
}
