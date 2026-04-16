<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refund_amount' => 'required|numeric|min:0.01',
            'reason'        => 'sometimes|nullable|string|max:500',
            'refund_type'   => 'sometimes|string|in:full,partial',
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.required' => '退款金额不能为空',
            'refund_amount.numeric'  => '退款金额必须为数字',
            'refund_amount.min'      => '退款金额必须大于0',
            'reason.max'             => '退款原因不能超过500字',
            'refund_type.in'         => '退款类型只能是 full 或 partial',
        ];
    }
}
