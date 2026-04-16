<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 佣金规则表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：base_rate 必填，min_rate < max_rate
 *  - PUT / PATCH（update）：所有字段均为 sometimes
 */
class CommissionRuleRequest extends FormRequest
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
            'merchant_id'      => 'nullable|integer|exists:jh_merchants,id',
            'store_id'         => 'nullable|integer|exists:jh_stores,id',
            'rule_type'        => 'sometimes|string|in:default,vip,promo',
            'tier_name'        => 'sometimes|string|max:50',
            'base_rate'        => 'required|numeric|min:0|max:100',
            'volume_discount'  => 'sometimes|numeric|min:0|max:100',
            'loyalty_discount' => 'sometimes|numeric|min:0|max:100',
            'min_rate'         => 'sometimes|numeric|min:0|max:100',
            'max_rate'         => 'sometimes|numeric|min:0|max:100',
            'enabled'          => 'sometimes|boolean',
        ];
    }

    /**
     * 更新规则（PUT / PATCH）
     */
    private function updateRules(): array
    {
        return [
            'merchant_id'      => 'sometimes|nullable|integer|exists:jh_merchants,id',
            'store_id'         => 'sometimes|nullable|integer|exists:jh_stores,id',
            'rule_type'        => 'sometimes|string|in:default,vip,promo',
            'tier_name'        => 'sometimes|string|max:50',
            'base_rate'        => 'sometimes|numeric|min:0|max:100',
            'volume_discount'  => 'sometimes|numeric|min:0|max:100',
            'loyalty_discount' => 'sometimes|numeric|min:0|max:100',
            'min_rate'         => 'sometimes|numeric|min:0|max:100',
            'max_rate'         => 'sometimes|numeric|min:0|max:100',
            'enabled'          => 'sometimes|boolean',
        ];
    }

    /**
     * 追加自定义验证：min_rate < max_rate
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            $minRate = $this->input('min_rate');
            $maxRate = $this->input('max_rate');

            // 两者都存在时才校验
            if ($minRate !== null && $maxRate !== null) {
                if (bccomp((string) $minRate, (string) $maxRate, 2) >= 0) {
                    $v->errors()->add('min_rate', '最低费率必须小于最高费率。');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'merchant_id.exists'   => '指定商户不存在',
            'store_id.exists'      => '指定站点不存在',
            'base_rate.required'   => '基础费率不能为空',
            'base_rate.numeric'    => '基础费率必须是数字',
            'base_rate.min'        => '基础费率不能小于0',
            'base_rate.max'        => '基础费率不能超过100',
            'min_rate.numeric'     => '最低费率必须是数字',
            'min_rate.min'         => '最低费率不能小于0',
            'min_rate.max'         => '最低费率不能超过100',
            'max_rate.numeric'     => '最高费率必须是数字',
            'max_rate.min'         => '最高费率不能小于0',
            'max_rate.max'         => '最高费率不能超过100',
            'rule_type.in'         => '规则类型必须为 default/vip/promo 之一',
            'tier_name.max'        => '等级名称最多50个字符',
            'volume_discount.min'  => '成交量奖励不能小于0',
            'volume_discount.max'  => '成交量奖励不能超过100',
            'loyalty_discount.min' => '忠诚度奖励不能小于0',
            'loyalty_discount.max' => '忠诚度奖励不能超过100',
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
