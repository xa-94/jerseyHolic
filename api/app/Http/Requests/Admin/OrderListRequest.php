<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 订单列表查询参数
 */
class OrderListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword'          => 'nullable|string|max:100',
            'pay_status'       => 'nullable|integer|in:1,2,3,4,5,6,7,8,9',
            'shipment_status'  => 'nullable|integer|in:0,1,2,3,4,5',
            'refund_status'    => 'nullable|integer|in:0,1,2,3,4,5',
            'date_from'        => 'nullable|date',
            'date_to'          => 'nullable|date',
            'domain'           => 'nullable|string|max:128',
            'pay_type'         => 'nullable|string|max:32',
            'sku_type'         => 'nullable|string|max:32',
            'per_page'         => 'nullable|integer|min:1|max:200',
            'page'             => 'nullable|integer|min:1',
        ];
    }
}
