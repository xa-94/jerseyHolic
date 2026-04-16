<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\MerchantPaymentGroupMapping;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 商户-支付分组映射表单验证（Admin 端 — 创建 & 更新）
 *
 * POST（store）：pay_method / payment_group_id 必填
 * PUT / PATCH（update）：所有字段 sometimes
 */
class PaymentGroupMappingRequest extends FormRequest
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

    private function createRules(): array
    {
        $payMethods = implode(',', MerchantPaymentGroupMapping::PAY_METHODS);

        return [
            'pay_method'         => "required|string|in:{$payMethods}",
            'payment_group_id'   => 'required|integer|exists:payment_account_groups,id',
            'priority'           => 'sometimes|integer|min:0|max:999',
        ];
    }

    private function updateRules(): array
    {
        return [
            'payment_group_id' => 'sometimes|integer|exists:payment_account_groups,id',
            'priority'         => 'sometimes|integer|min:0|max:999',
        ];
    }

    public function messages(): array
    {
        return [
            'pay_method.required'         => '支付方式不能为空',
            'pay_method.in'               => '支付方式不合法，允许值：paypal、stripe、credit_card、antom',
            'payment_group_id.required'   => '支付分组 ID 不能为空',
            'payment_group_id.exists'     => '指定的支付分组不存在',
            'priority.integer'            => '优先级必须为整数',
            'priority.min'                => '优先级最小值为 0',
            'priority.max'                => '优先级最大值为 999',
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
