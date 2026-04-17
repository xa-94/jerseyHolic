<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 批量商品同步请求验证
 *
 * POST /api/v1/merchant/sync/batch
 */
class SyncBatchRequest extends FormRequest
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
            'store_id'             => 'required|string',
            'master_product_ids'   => 'required|array|min:1|max:500',
            'master_product_ids.*' => 'string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required'           => '目标店铺 ID 不能为空',
            'store_id.string'             => '店铺 ID 格式无效',
            'master_product_ids.required' => '请选择要同步的商品',
            'master_product_ids.array'    => '商品 ID 列表格式无效',
            'master_product_ids.min'      => '至少选择一个商品',
            'master_product_ids.max'      => '单次最多同步 500 个商品',
            'master_product_ids.*.string' => '商品 ID 格式无效',
        ];
    }
}
