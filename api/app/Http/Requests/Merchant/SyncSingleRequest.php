<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 单商品同步请求验证
 *
 * POST /api/v1/merchant/sync/{masterProductId}/single
 */
class SyncSingleRequest extends FormRequest
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
            'store_id'     => 'required|string',
            'sync_rule_id' => 'nullable|integer|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required'     => '目标店铺 ID 不能为空',
            'store_id.string'       => '店铺 ID 格式无效',
            'sync_rule_id.integer'  => '同步规则 ID 格式无效',
            'sync_rule_id.min'      => '同步规则 ID 格式无效',
        ];
    }
}
