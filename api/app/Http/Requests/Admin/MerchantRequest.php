<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 商户表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：merchant_name/email/password/contact_name 必填
 *  - PUT / PATCH（update）：所有字段均为 sometimes（可选）
 */
class MerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $id       = $this->route('id') ?? $this->route('merchant');

        if ($isUpdate) {
            return $this->updateRules($id);
        }

        return $this->createRules();
    }

    /**
     * 创建商户规则（POST）
     */
    private function createRules(): array
    {
        return [
            'merchant_name' => 'required|string|max:100',
            'email'         => 'required|email|max:255|unique:jh_merchants,email',
            'password'      => 'required|string|min:8',
            'contact_name'  => 'required|string|max:100',
            'phone'         => 'nullable|string|max:30',
            'level'         => 'sometimes|string|in:starter,standard,advanced,vip',
        ];
    }

    /**
     * 更新商户规则（PUT / PATCH）
     */
    private function updateRules(?int $id): array
    {
        $uniqueRule = 'sometimes|email|max:255|unique:jh_merchants,email';
        if ($id) {
            $uniqueRule .= ",{$id}";
        }

        return [
            'merchant_name' => 'sometimes|string|max:100',
            'email'         => $uniqueRule,
            'password'      => 'sometimes|string|min:8',
            'contact_name'  => 'sometimes|string|max:100',
            'phone'         => 'nullable|string|max:30',
            'level'         => 'sometimes|string|in:starter,standard,advanced,vip',
        ];
    }

    public function messages(): array
    {
        return [
            'merchant_name.required'  => '商户名称不能为空',
            'merchant_name.max'       => '商户名称最多100个字符',
            'email.required'          => '邮箱地址不能为空',
            'email.email'             => '邮箱格式不正确',
            'email.unique'            => '该邮箱已被注册',
            'password.required'       => '密码不能为空',
            'password.min'            => '密码至少需要8位字符',
            'contact_name.required'   => '联系人姓名不能为空',
            'phone.max'               => '手机号最多30个字符',
            'level.in'                => '商户等级值非法，允许值：starter、standard、advanced、vip',
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
