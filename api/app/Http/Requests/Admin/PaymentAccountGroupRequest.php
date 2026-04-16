<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\PaymentAccountGroup;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 支付账号分组表单验证（Admin 端 — 创建 & 更新）
 *
 * POST（store）：name / type 必填
 * PUT / PATCH（update）：所有字段 sometimes
 */
class PaymentAccountGroupRequest extends FormRequest
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
        $types      = implode(',', [
            PaymentAccountGroup::TYPE_PAYPAL,
            PaymentAccountGroup::TYPE_CREDIT_CARD,
            PaymentAccountGroup::TYPE_STRIPE,
            PaymentAccountGroup::TYPE_ANTOM,
        ]);
        $groupTypes = implode(',', PaymentAccountGroup::GROUP_TYPES);

        return [
            'name'               => 'required|string|max:64',
            'type'               => "required|string|in:{$types}",
            'group_type'         => "sometimes|string|in:{$groupTypes}",
            'description'        => 'nullable|string|max:255',
            'is_blacklist_group' => 'sometimes|in:0,1',
            'status'             => 'sometimes|in:0,1',
        ];
    }

    private function updateRules(): array
    {
        $types      = implode(',', [
            PaymentAccountGroup::TYPE_PAYPAL,
            PaymentAccountGroup::TYPE_CREDIT_CARD,
            PaymentAccountGroup::TYPE_STRIPE,
            PaymentAccountGroup::TYPE_ANTOM,
        ]);
        $groupTypes = implode(',', PaymentAccountGroup::GROUP_TYPES);

        return [
            'name'               => 'sometimes|string|max:64',
            'type'               => "sometimes|string|in:{$types}",
            'group_type'         => "sometimes|string|in:{$groupTypes}",
            'description'        => 'nullable|string|max:255',
            'is_blacklist_group' => 'sometimes|in:0,1',
            'status'             => 'sometimes|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => '分组名称不能为空',
            'name.max'       => '分组名称最多64个字符',
            'type.required'  => '分组类型不能为空',
            'type.in'        => '分组类型不合法，允许值：paypal、credit_card、stripe、antom',
            'group_type.in'  => '分组策略类型不合法',
            'description.max' => '描述最多255个字符',
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
