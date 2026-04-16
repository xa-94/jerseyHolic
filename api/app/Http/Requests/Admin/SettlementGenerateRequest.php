<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 结算单手动生成请求验证
 *
 * POST /api/v1/admin/settlements/generate
 */
class SettlementGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'merchant_id'  => 'nullable|integer|exists:merchants,id',
            'period_start' => 'required|date|date_format:Y-m-d',
            'period_end'   => 'required|date|date_format:Y-m-d|after_or_equal:period_start',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'merchant_id.integer'            => '商户 ID 必须为整数',
            'merchant_id.exists'             => '指定商户不存在',
            'period_start.required'          => '结算周期开始日期为必填项',
            'period_start.date'              => '结算周期开始日期格式不正确',
            'period_start.date_format'       => '结算周期开始日期格式须为 Y-m-d',
            'period_end.required'            => '结算周期结束日期为必填项',
            'period_end.date'                => '结算周期结束日期格式不正确',
            'period_end.date_format'         => '结算周期结束日期格式须为 Y-m-d',
            'period_end.after_or_equal'      => '结算周期结束日期必须晚于或等于开始日期',
        ];
    }
}
