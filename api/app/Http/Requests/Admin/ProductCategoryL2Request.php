<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 二级品类 L2 表单验证（Admin 端）
 *
 * 用于创建/更新 ProductCategoryL2 的参数校验。
 */
class ProductCategoryL2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'l1_id'        => ($isUpdate ? 'sometimes|' : '') . 'required|integer|exists:central.product_categories_l1,id',
            'code'         => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:50|regex:/^[a-z][a-z0-9_]*$/',
            'name'         => ($isUpdate ? 'sometimes|' : '') . 'required|array',
            'name.en'      => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:100',
            'name.*'       => 'nullable|string|max:100',
            'is_sensitive' => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer|min:0',
            'status'       => 'sometimes|integer|in:0,1',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'l1_id.required'        => '一级品类ID不能为空',
            'l1_id.exists'          => '一级品类不存在',
            'code.required'         => '品类编码不能为空',
            'code.max'              => '品类编码最多50个字符',
            'code.regex'            => '品类编码只能包含小写字母、数字和下划线，且以字母开头',
            'name.required'         => '品类名称不能为空',
            'name.en.required'      => '必须提供英文品类名称',
            'name.en.max'           => '英文品类名称最多100个字符',
            'is_sensitive.boolean'  => '敏感标记必须为布尔值',
            'sort_order.integer'    => '排序值必须为整数',
            'sort_order.min'        => '排序值不能为负数',
            'status.in'             => '状态值只能为0或1',
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
