<?php

declare(strict_types=1);

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 批量删除商品请求验证
 *
 * POST /api/v1/merchant/products/batch-delete
 */
class BatchProductDeleteRequest extends FormRequest
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
            'ids'   => 'required|array|min:1|max:100',
            'ids.*' => 'integer|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => '请选择要删除的商品',
            'ids.min'      => '至少选择一个商品',
            'ids.max'      => '单次最多删除 100 个商品',
        ];
    }
}
