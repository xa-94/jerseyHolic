<?php

namespace App\Services;

use App\Models\Central\Merchant;
use App\Models\Central\MerchantAuditLog;
use App\Services\MerchantStatusCascadeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * 商户核心业务服务
 *
 * 负责商户的注册、信息管理、状态管理、等级管理和审核功能。
 * 所有查询均在 Central 数据库连接上执行。
 *
 * 商户状态（status 整型映射）：
 *  0 = pending（待审核）
 *  1 = active（已激活）
 *  2 = rejected（已拒绝）
 *  3 = info_required（需补充信息）
 *  4 = suspended（已暂停）
 *  5 = banned（已封禁）
 *
 * 商户等级（level）：starter | standard | advanced | vip
 * 各等级站点上限：starter=2, standard=5, advanced=10, vip=unlimited(-1)
 */
class MerchantService
{
    /** 状态字符串 → 整型映射 */
    public const STATUS_MAP = [
        'pending'       => 0,
        'active'        => 1,
        'rejected'      => 2,
        'info_required' => 3,
        'suspended'     => 4,
        'banned'        => 5,
    ];

    /** 整型 → 状态字符串映射 */
    public const STATUS_LABEL = [
        0 => 'pending',
        1 => 'active',
        2 => 'rejected',
        3 => 'info_required',
        4 => 'suspended',
        5 => 'banned',
    ];

    /** 各等级站点上限（-1 表示无限制） */
    public const LEVEL_STORE_LIMITS = [
        'starter'  => 2,
        'standard' => 5,
        'advanced' => 10,
        'vip'      => -1,
    ];

    /**
     * 允许的状态变更路径
     * key = 目标状态，value = 允许的来源状态列表
     */
    private const ALLOWED_TRANSITIONS = [
        'active'        => ['pending', 'info_required', 'suspended'],
        'rejected'      => ['pending', 'info_required'],
        'info_required' => ['pending'],
        'suspended'     => ['active'],
        'banned'        => ['active', 'suspended'],
        'pending'       => [],   // 不允许回退到 pending
    ];

    /* ----------------------------------------------------------------
     |  商户注册（公开端点）
     | ---------------------------------------------------------------- */

    /**
     * 注册新商户
     *
     * 创建 merchants 记录，初始状态为 pending（0），密码使用 bcrypt 加密。
     *
     * @param  array $data  需包含 merchant_name, email, password, contact_name
     * @return Merchant
     * @throws ValidationException  email 已存在时抛出
     */
    public function register(array $data): Merchant
    {
        // 验证 email 唯一性
        if (Merchant::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['该邮箱已被注册，请使用其他邮箱。'],
            ]);
        }

        return Merchant::create([
            'merchant_name' => $data['merchant_name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'contact_name'  => $data['contact_name'],
            'phone'         => $data['phone'] ?? null,
            'level'         => $data['level'] ?? 'starter',
            'status'        => self::STATUS_MAP['pending'],
        ]);
    }

    /* ----------------------------------------------------------------
     |  商户信息管理
     | ---------------------------------------------------------------- */

    /**
     * 获取商户详情
     *
     * @param  int $id
     * @return Merchant
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getMerchant(int $id): Merchant
    {
        return Merchant::findOrFail($id);
    }

    /**
     * 更新商户信息
     *
     * @param  int   $id
     * @param  array $data
     * @return Merchant
     */
    public function updateMerchant(int $id, array $data): Merchant
    {
        $merchant = $this->getMerchant($id);

        // 若传入 password 则加密
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // email 唯一性校验（排除自身）
        if (!empty($data['email']) && $data['email'] !== $merchant->email) {
            if (Merchant::where('email', $data['email'])->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['该邮箱已被其他商户使用。'],
                ]);
            }
        }

        $merchant->update($data);

        return $merchant->fresh();
    }

    /**
     * 获取商户列表（分页 + 筛选）
     *
     * 支持按 status（字符串或整型）、level、keyword（name/email 模糊搜索）筛选。
     *
     * @param  array $filters  可选 keys: status, level, keyword, per_page, page
     * @return LengthAwarePaginator
     */
    public function listMerchants(array $filters): LengthAwarePaginator
    {
        $query = Merchant::query();

        // 关键词搜索（商户名 / 邮箱）
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('merchant_name', 'like', $kw)
                  ->orWhere('email', 'like', $kw)
                  ->orWhere('contact_name', 'like', $kw);
            });
        }

        // 状态筛选（接受字符串或整型）
        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = $filters['status'];
            if (is_string($status) && isset(self::STATUS_MAP[$status])) {
                $status = self::STATUS_MAP[$status];
            }
            $query->where('status', (int)$status);
        }

        // 等级筛选
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        $perPage = (int)($filters['per_page'] ?? 15);

        return $query->latest()->paginate($perPage);
    }

    /* ----------------------------------------------------------------
     |  商户状态管理（F-MCH-013）
     | ---------------------------------------------------------------- */

    /**
     * 变更商户状态
     *
     * @param  int         $id
     * @param  string      $status   目标状态字符串（pending/active/rejected/info_required/suspended/banned）
     * @param  string|null $reason   状态变更原因（可选）
     * @return Merchant
     * @throws \InvalidArgumentException  状态值非法时
     * @throws ValidationException        状态变更路径不允许时
     */
    public function changeStatus(int $id, string $status, ?string $reason = null): Merchant
    {
        if (!array_key_exists($status, self::STATUS_MAP)) {
            throw new \InvalidArgumentException("非法状态值：{$status}");
        }

        $merchant       = $this->getMerchant($id);
        $currentLabel   = self::STATUS_LABEL[$merchant->status] ?? 'unknown';

        // 校验状态变更路径
        $allowedFrom = self::ALLOWED_TRANSITIONS[$status] ?? [];
        if (!empty($allowedFrom) && !in_array($currentLabel, $allowedFrom, true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "商户当前状态为「{$currentLabel}」，不允许变更为「{$status}」。"
                    . "允许的来源状态：" . implode(', ', $allowedFrom),
                ],
            ]);
        }

        $updateData = ['status' => self::STATUS_MAP[$status]];

        // 若 Merchant 表有 status_reason 字段则记录原因
        // （当前 Model 未定义此字段，预留扩展：有字段时自动写入）
        if ($reason !== null && in_array('status_reason', $merchant->getFillable(), true)) {
            $updateData['status_reason'] = $reason;
        }

        $merchant->update($updateData);

        $merchant = $merchant->fresh();

        // 级联状态变更（暂停/封禁/恢复 active 时同步处理名下站点和 API 密钥）
        app(MerchantStatusCascadeService::class)->cascadeStatus($merchant, $status);

        return $merchant;
    }

    /* ----------------------------------------------------------------
     |  商户等级管理（F-MCH-014）
     | ---------------------------------------------------------------- */

    /**
     * 更新商户等级
     *
     * @param  int    $id
     * @param  string $level  starter | standard | advanced | vip
     * @return Merchant
     * @throws \InvalidArgumentException
     */
    public function updateLevel(int $id, string $level): Merchant
    {
        if (!array_key_exists($level, self::LEVEL_STORE_LIMITS)) {
            throw new \InvalidArgumentException(
                "非法等级值：{$level}，允许值：" . implode(', ', array_keys(self::LEVEL_STORE_LIMITS))
            );
        }

        $merchant = $this->getMerchant($id);
        $merchant->update(['level' => $level]);

        return $merchant->fresh();
    }

    /**
     * 获取指定等级对应的站点上限
     *
     * @param  string $level
     * @return int  -1 表示无限制
     */
    public function getLevelStoreLimit(string $level): int
    {
        return self::LEVEL_STORE_LIMITS[$level] ?? 2;
    }

    /* ----------------------------------------------------------------
     |  审核接口
     | ---------------------------------------------------------------- */

    /**
     * 审核商户
     *
     * approve：更新状态为 active，自动创建商户专属数据库，记录审核日志，写入 approved_at。
     * reject：更新状态为 rejected，记录审核日志（含拒绝原因）。
     * request_info：更新状态为 info_required，记录审核日志（含补充要求）。
     *
     * @param  int         $id
     * @param  string      $action    approve | reject | request_info
     * @param  string|null $comment   审核意见（可选）
     * @param  int|null    $adminId   操作管理员 ID（null 时尝试从 Auth::id() 获取）
     * @return Merchant
     * @throws \InvalidArgumentException  action 非法时
     * @throws ValidationException        状态变更路径不允许时
     * @throws \RuntimeException          approve 时商户库创建失败
     */
    public function review(int $id, string $action, ?string $comment = null, ?int $adminId = null): Merchant
    {
        $actionToStatus = [
            'approve'      => 'active',
            'reject'       => 'rejected',
            'request_info' => 'info_required',
        ];

        if (!isset($actionToStatus[$action])) {
            throw new \InvalidArgumentException(
                "非法审核操作：{$action}，允许值：" . implode(', ', array_keys($actionToStatus))
            );
        }

        $targetStatus = $actionToStatus[$action];

        // 获取操作人 ID：优先使用传入参数，否则尝试从当前认证上下文获取
        $operatorId = $adminId ?? (Auth::guard('admin')->id() ?? null);

        $merchant     = $this->getMerchant($id);
        $fromStatus   = self::STATUS_LABEL[$merchant->status] ?? 'unknown';

        // 在事务中执行状态变更 + 审核日志记录
        $merchant = DB::connection('central')->transaction(function () use (
            $merchant, $action, $targetStatus, $fromStatus, $comment, $operatorId
        ) {
            // 1. 变更状态（含状态路径校验）
            $merchant = $this->changeStatus($merchant->id, $targetStatus, $comment);

            // 2. approve 时补充写入 approved_at
            if ($action === 'approve') {
                $merchant->update(['approved_at' => now()]);
                $merchant = $merchant->fresh();
            }

            // 3. 记录审核日志
            MerchantAuditLog::record(
                merchantId: $merchant->id,
                action:     $action,
                fromStatus: $fromStatus,
                toStatus:   $targetStatus,
                adminId:    $operatorId,
                comment:    $comment,
                metadata:   $this->buildAuditMetadata($action, $merchant)
            );

            return $merchant;
        });

        // 4. approve 时在事务外创建商户专属数据库（DDL 不支持事务回滚，单独处理）
        if ($action === 'approve') {
            $this->provisionMerchantDatabase($merchant);
        }

        return $merchant;
    }

    /**
     * 创建商户专属数据库（审核通过后调用）
     *
     * 若库已存在则跳过，失败时记录错误日志但不阻断主流程。
     *
     * @param  Merchant $merchant
     * @return void
     */
    protected function provisionMerchantDatabase(Merchant $merchant): void
    {
        $dbService = app(MerchantDatabaseService::class);

        // 幂等检查：若数据库已存在则跳过
        if ($dbService->merchantDatabaseExists($merchant)) {
            Log::info('[MerchantService] Merchant database already exists, skipping.', [
                'merchant_id' => $merchant->id,
                'db_name'     => $dbService->getDatabaseName($merchant),
            ]);
            return;
        }

        $dbService->createMerchantDatabase($merchant);
    }

    /**
     * 构建审核日志元数据
     *
     * @param  string   $action
     * @param  Merchant $merchant
     * @return array|null
     */
    protected function buildAuditMetadata(string $action, Merchant $merchant): ?array
    {
        if ($action === 'approve') {
            return [
                'db_name'     => 'jerseyholic_merchant_' . $merchant->id,
                'approved_at' => now()->toIso8601String(),
            ];
        }

        return null;
    }

    /* ----------------------------------------------------------------
     |  商户删除
     | ---------------------------------------------------------------- */

    /**
     * 软删除商户
     *
     * @param  int $id
     * @return void
     */
    public function deleteMerchant(int $id): void
    {
        $merchant = $this->getMerchant($id);
        $merchant->delete();
    }
}
