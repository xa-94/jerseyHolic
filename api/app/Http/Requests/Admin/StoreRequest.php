<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 站点表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：merchant_id / store_name / store_code / domain 必填
 *  - PUT / PATCH（update）：所有字段均为 sometimes（可选）
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $id       = $this->route('id') ?? $this->route('store');

        if ($isUpdate) {
            return $this->updateRules($id);
        }

        return $this->createRules();
    }

    /**
     * 创建站点规则（POST）
     */
    private function createRules(): array
    {
        return [
            'merchant_id' => 'required|integer|exists:jh_merchants,id',
            'store_name'  => 'required|string|max:100',
            'store_code'  => 'required|string|max:50|unique:stores,store_code',
            'domain'      => 'required|string|max:255',

            // 可选配置字段
            'target_markets'       => 'nullable|array',
            'supported_languages'  => 'nullable|array',
            'supported_currencies' => 'nullable|array',
            'product_categories'   => 'nullable|array',
            'payment_preferences'  => 'nullable|array',
            'logistics_config'     => 'nullable|array',
            'theme_config'         => 'nullable|array',
        ];
    }

    /**
     * 更新站点规则（PUT / PATCH）
     */
    private function updateRules(?int $id): array
    {
        $storeCodeRule = 'sometimes|string|max:50|unique:stores,store_code';
        if ($id) {
            $storeCodeRule .= ",{$id}";
        }

        return [
            'store_name'           => 'sometimes|string|max:100',
            'store_code'           => $storeCodeRule,

            // 可选配置字段
            'target_markets'       => 'nullable|array',
            'supported_languages'  => 'nullable|array',
            'supported_currencies' => 'nullable|array',
            'product_categories'   => 'nullable|array',
            'payment_preferences'  => 'nullable|array',
            'logistics_config'     => 'nullable|array',
            'theme_config'         => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'merchant_id.required' => '商户ID不能为空',
            'merchant_id.exists'   => '指定商户不存在',
            'store_name.required'  => '站点名称不能为空',
            'store_name.max'       => '站点名称最多100个字符',
            'store_code.required'  => '站点编码不能为空',
            'store_code.max'       => '站点编码最多50个字符',
            'store_code.unique'    => '该站点编码已被占用',
            'domain.required'      => '域名不能为空',
            'domain.max'           => '域名最多255个字符',
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
