<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|integer|min:0',
            'remark' => 'sometimes|nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => '状态值不能为空',
            'status.integer'  => '状态值必须为整数',
            'remark.max'      => '备注内容不能超过500字',
        ];
    }
}
