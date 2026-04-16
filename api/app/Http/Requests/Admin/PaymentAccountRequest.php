<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\PaymentAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 支付账号表单验证（Admin 端 — 创建 & 更新）
 *
 * POST（store）：account / client_id / client_secret / pay_method 必填
 * PUT / PATCH（update）：所有字段 sometimes
 */
class PaymentAccountRequest extends FormRequest
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
        return [
            'account'             => 'required|string|max:128',
            'email'               => 'nullable|email|max:128',
            'client_id'           => 'required|string|max:255',
            'client_secret'       => 'required|string|max:500',
            'merchant_id_external' => 'nullable|string|max:128',
            'pay_method'          => 'required|string|in:paypal,credit_card,stripe,antom,payssion',
            'category_id'         => 'nullable|integer|exists:jh_payment_account_groups,id',
            'cc_category_id'      => 'nullable|integer|exists:jh_payment_account_groups,id',
            'min_money'           => 'nullable|numeric|min:0',
            'max_money'           => 'nullable|numeric|min:0',
            'limit_money'         => 'nullable|numeric|min:0',
            'daily_limit_money'   => 'nullable|numeric|min:0',
            'priority'            => 'nullable|integer|min:0',
            'max_num'             => 'nullable|integer|min:0',
            'domain'              => 'nullable|string|max:255',
            'webhook_id'          => 'nullable|string|max:255',
            'success_url'         => 'nullable|url|max:512',
            'cancel_url'          => 'nullable|url|max:512',
            'pay_url'             => 'nullable|url|max:512',
        ];
    }

    private function updateRules(): array
    {
        return [
            'account'             => 'sometimes|string|max:128',
            'email'               => 'nullable|email|max:128',
            'client_id'           => 'sometimes|string|max:255',
            'client_secret'       => 'sometimes|string|max:500',
            'merchant_id_external' => 'nullable|string|max:128',
            'pay_method'          => 'sometimes|string|in:paypal,credit_card,stripe,antom,payssion',
            'category_id'         => 'nullable|integer|exists:jh_payment_account_groups,id',
            'cc_category_id'      => 'nullable|integer|exists:jh_payment_account_groups,id',
            'min_money'           => 'nullable|numeric|min:0',
            'max_money'           => 'nullable|numeric|min:0',
            'limit_money'         => 'nullable|numeric|min:0',
            'daily_limit_money'   => 'nullable|numeric|min:0',
            'priority'            => 'nullable|integer|min:0',
            'max_num'             => 'nullable|integer|min:0',
            'domain'              => 'nullable|string|max:255',
            'webhook_id'          => 'nullable|string|max:255',
            'success_url'         => 'nullable|url|max:512',
            'cancel_url'          => 'nullable|url|max:512',
            'pay_url'             => 'nullable|url|max:512',
            'status'              => 'sometimes|in:0,1',
            'permission'          => 'sometimes|integer|in:1,2,3',
        ];
    }

    public function messages(): array
    {
        return [
            'account.required'       => '账号标识不能为空',
            'account.max'            => '账号标识最多128个字符',
            'email.email'            => '邮箱格式不正确',
            'client_id.required'     => 'Client ID 不能为空',
            'client_secret.required' => 'Client Secret 不能为空',
            'pay_method.required'    => '支付方式不能为空',
            'pay_method.in'          => '支付方式不合法，允许值：paypal、credit_card、stripe、antom、payssion',
            'category_id.exists'     => '指定的 PayPal 分组不存在',
            'cc_category_id.exists'  => '指定的信用卡分组不存在',
            'min_money.min'          => '最小金额不能为负数',
            'max_money.min'          => '最大金额不能为负数',
            'success_url.url'        => '成功回调 URL 格式不正确',
            'cancel_url.url'         => '取消回调 URL 格式不正确',
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
