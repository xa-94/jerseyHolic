<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 安全描述表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：product_category / safe_name / safe_description 必填
 *  - PUT / PATCH（update）：所有字段均为 sometimes（可选）
 *
 * PayPal 限制：safe_description ≤ 127 字符，safe_name ≤ 200 字符
 */
class SafeDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        if ($isUpdate) {
            return $this->updateRules();
        }

        return $this->createRules();
    }

    /**
     * 创建规则（POST）
     */
    private function createRules(): array
    {
        return [
            'store_id'           => 'nullable|integer|exists:jh_stores,id',
            'product_category'   => 'required|string|max:50',
            'safe_name'          => 'required|string|max:200',
            'safe_description'   => 'required|string|max:127',
            'safe_category_code' => 'nullable|string|max:20',
            'weight'             => 'sometimes|integer|min:1|max:100',
            'status'             => 'sometimes|integer|in:0,1',
        ];
    }

    /**
     * 更新规则（PUT / PATCH）
     */
    private function updateRules(): array
    {
        return [
            'store_id'           => 'nullable|integer|exists:jh_stores,id',
            'product_category'   => 'sometimes|string|max:50',
            'safe_name'          => 'sometimes|string|max:200',
            'safe_description'   => 'sometimes|string|max:127',
            'safe_category_code' => 'nullable|string|max:20',
            'weight'             => 'sometimes|integer|min:1|max:100',
            'status'             => 'sometimes|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'product_category.required'   => '商品分类不能为空',
            'product_category.max'        => '商品分类最多50个字符',
            'safe_name.required'          => '安全名称不能为空',
            'safe_name.max'               => '安全名称最多200个字符',
            'safe_description.required'   => '安全描述不能为空',
            'safe_description.max'        => '安全描述最多127个字符（PayPal 限制）',
            'safe_category_code.max'      => '安全分类代码最多20个字符',
            'weight.min'                  => '权重最小为1',
            'weight.max'                  => '权重最大为100',
            'status.in'                   => '状态值非法，允许值：0（禁用）、1（启用）',
            'store_id.exists'             => '站点不存在',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'code'    => 42200,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
