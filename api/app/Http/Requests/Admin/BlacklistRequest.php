<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\Blacklist;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * 黑名单表单验证（Admin 端 — 创建 & 更新）
 *
 * 根据请求方法自动切换规则：
 *  - POST（store）：dimension / value / reason 必填
 *  - PUT / PATCH（update）：reason / expires_at 可选
 */
class BlacklistRequest extends FormRequest
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
     * 创建黑名单规则（POST）
     */
    private function createRules(): array
    {
        return [
            'dimension'   => ['required', 'string', Rule::in(Blacklist::DIMENSIONS)],
            'value'       => 'required|string|max:255',
            'reason'      => 'required|string|max:500',
            'merchant_id' => 'nullable|integer|exists:jh_merchants,id',
            'expires_at'  => 'nullable|date|after:now',
        ];
    }

    /**
     * 更新黑名单规则（PUT / PATCH）
     */
    private function updateRules(): array
    {
        return [
            'reason'     => 'sometimes|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'dimension.required'   => '黑名单维度不能为空',
            'dimension.in'         => '黑名单维度值非法，允许值：' . implode(', ', Blacklist::DIMENSIONS),
            'value.required'       => '黑名单值不能为空',
            'value.max'            => '黑名单值最多255个字符',
            'reason.required'      => '添加原因不能为空',
            'reason.max'           => '添加原因最多500个字符',
            'merchant_id.exists'   => '指定的商户不存在',
            'expires_at.date'      => '过期时间格式不正确',
            'expires_at.after'     => '过期时间必须是未来时间',
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
