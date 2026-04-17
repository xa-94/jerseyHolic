<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 一级品类 L1 表单验证（Admin 端）
 *
 * 用于创建/更新 ProductCategoryL1 的参数校验。
 */
class ProductCategoryL1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $id       = $this->route('id');

        $rules = [
            'code'            => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:50|regex:/^[a-z][a-z0-9_]*$/|unique:central.product_categories_l1,code' . ($isUpdate ? ",{$id}" : ''),
            'name'            => ($isUpdate ? 'sometimes|' : '') . 'required|array',
            'name.en'         => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:100',
            'name.*'          => 'nullable|string|max:100',
            'icon'            => 'nullable|string|max:255',
            'is_sensitive'    => 'sometimes|boolean',
            'sensitive_ratio' => 'sometimes|numeric|min:0|max:100',
            'sort_order'      => 'sometimes|integer|min:0',
            'status'          => 'sometimes|integer|in:0,1',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'code.required'         => '品类编码不能为空',
            'code.max'              => '品类编码最多50个字符',
            'code.regex'            => '品类编码只能包含小写字母、数字和下划线，且以字母开头',
            'code.unique'           => '品类编码已存在',
            'name.required'         => '品类名称不能为空',
            'name.en.required'      => '必须提供英文品类名称',
            'name.en.max'           => '英文品类名称最多100个字符',
            'is_sensitive.boolean'  => '敏感标记必须为布尔值',
            'sensitive_ratio.numeric' => '敏感占比必须为数值',
            'sensitive_ratio.min'   => '敏感占比不能小于0',
            'sensitive_ratio.max'   => '敏感占比不能大于100',
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
