<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    /**
     * 所有用户均可发起加购请求（登录用户 + 游客）
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|min:1',
            'sku_id'     => 'nullable|integer|min:1',
            'quantity'   => 'required|integer|min:1|max:999',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => '请选择商品',
            'product_id.integer'  => '商品 ID 格式错误',
            'sku_id.integer'      => 'SKU ID 格式错误',
            'quantity.required'   => '请填写数量',
            'quantity.integer'    => '数量必须为整数',
            'quantity.min'        => '数量最少为 1',
            'quantity.max'        => '单次加购数量不能超过 999',
        ];
    }
}
