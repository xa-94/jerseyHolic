<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CommissionRuleRequest;
use App\Http\Resources\Admin\CommissionRuleResource;
use App\Models\Central\CommissionRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台管理端 — 佣金规则 Controller（M3-012）
 *
 * 路由前缀：/api/v1/admin/commission-rules
 * 中间件：auth:sanctum + force.json + central.only
 *
 * 提供佣金规则的 CRUD 操作，支持按 merchant_id/store_id/enabled 筛选。
 */
class CommissionRuleController extends BaseAdminController
{
    /**
     * 佣金规则列表
     *
     * GET /api/v1/admin/commission-rules
     *
     * Query params:
     *  - merchant_id  int     按商户筛选
     *  - store_id     int     按站点筛选
     *  - enabled      int     按启用状态筛选（0/1）
     *  - rule_type    string  按规则类型筛选（default/vip/promo）
     *  - per_page     int     每页条数，默认 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = CommissionRule::with(['merchant', 'store']);

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', (int) $request->input('merchant_id'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->has('enabled') && $request->input('enabled') !== '') {
            $query->where('enabled', (int) $request->input('enabled'));
        }

        if ($request->filled('rule_type')) {
            $query->where('rule_type', $request->input('rule_type'));
        }

        $perPage   = (int) ($request->input('per_page', 15));
        $paginator = $query->latest()->paginate($perPage);

        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'list'     => CommissionRuleResource::collection($paginator->items()),
                'total'    => $paginator->total(),
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * 创建佣金规则
     *
     * POST /api/v1/admin/commission-rules
     */
    public function store(CommissionRuleRequest $request): JsonResponse
    {
        $data = $request->validated();

        // 默认值填充
        $data['rule_type']        = $data['rule_type'] ?? CommissionRule::RULE_TYPE_DEFAULT;
        $data['volume_discount']  = $data['volume_discount'] ?? '0.00';
        $data['loyalty_discount'] = $data['loyalty_discount'] ?? '0.00';
        $data['min_rate']         = $data['min_rate'] ?? '8.00';
        $data['max_rate']         = $data['max_rate'] ?? '35.00';
        $data['enabled']          = $data['enabled'] ?? CommissionRule::ENABLED;

        $rule = CommissionRule::create($data);
        $rule->load(['merchant', 'store']);

        return $this->success(new CommissionRuleResource($rule), '佣金规则创建成功');
    }

    /**
     * 佣金规则详情
     *
     * GET /api/v1/admin/commission-rules/{id}
     */
    public function show(int $id): JsonResponse
    {
        $rule = CommissionRule::with(['merchant', 'store'])->findOrFail($id);

        return $this->success(new CommissionRuleResource($rule));
    }

    /**
     * 更新佣金规则
     *
     * PUT /api/v1/admin/commission-rules/{id}
     */
    public function update(CommissionRuleRequest $request, int $id): JsonResponse
    {
        $rule = CommissionRule::findOrFail($id);
        $rule->update($request->validated());
        $rule->load(['merchant', 'store']);

        return $this->success(new CommissionRuleResource($rule), '佣金规则已更新');
    }

    /**
     * 删除佣金规则
     *
     * DELETE /api/v1/admin/commission-rules/{id}
     *
     * 仅允许删除未启用的规则。
     */
    public function destroy(int $id): JsonResponse
    {
        $rule = CommissionRule::findOrFail($id);

        if ($rule->enabled === CommissionRule::ENABLED) {
            return $this->error(40301, '已启用的佣金规则不能删除，请先禁用。');
        }

        $rule->delete();

        return $this->success(null, '佣金规则已删除');
    }
}
