<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 批量更新商品状态请求验证
 *
 * POST /api/v1/merchant/products/batch-status
 */
class BatchProductStatusRequest extends FormRequest
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
            'ids'    => 'required|array|min:1|max:100',
            'ids.*'  => 'integer|min:1',
            'status' => 'required|integer|in:0,1,2',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required'  => '请选择要操作的商品',
            'ids.min'       => '至少选择一个商品',
            'ids.max'       => '单次最多操作 100 个商品',
            'status.required' => '目标状态不能为空',
            'status.in'     => '状态值无效（0=禁用, 1=启用, 2=草稿）',
        ];
    }
}
