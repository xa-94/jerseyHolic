<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use App\Models\Merchant\SyncRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 更新同步规则请求验证
 *
 * PUT /api/v1/merchant/sync-rules/{id}
 */
class UpdateSyncRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'              => 'sometimes|string|max:100',
            'target_store_ids'  => 'sometimes|array|min:1',
            'target_store_ids.*' => 'integer|min:1',
            'excluded_store_ids'  => 'nullable|array',
            'excluded_store_ids.*' => 'integer|min:1',
            'sync_fields'       => 'sometimes|array|min:1',
            'sync_fields.*'     => 'string|max:50',
            'price_strategy'    => 'sometimes|string|in:' . implode(',', [
                SyncRule::PRICE_FIXED,
                SyncRule::PRICE_MULTIPLIER,
                SyncRule::PRICE_CUSTOM,
            ]),
            'price_multiplier'  => 'sometimes|numeric|min:0.01|max:99.99',
            'auto_sync'         => 'nullable|boolean',
            'status'            => 'sometimes|integer|in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max'                  => '规则名称长度不能超过 100 个字符',
            'target_store_ids.min'      => '至少选择一个目标站点',
            'sync_fields.min'           => '至少选择一个同步字段',
            'price_strategy.in'         => '价格策略无效（可选：fixed, multiplier, custom）',
            'price_multiplier.numeric'  => '价格乘数必须为数字',
            'price_multiplier.min'      => '价格乘数不能小于 0.01',
            'price_multiplier.max'      => '价格乘数不能大于 99.99',
            'status.in'                 => '状态值无效（0=禁用, 1=启用）',
        ];
    }
}
