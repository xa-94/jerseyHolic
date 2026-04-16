<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 结算单审核操作请求验证（M3-014）
 *
 * 适用于 reject / cancel 操作（需要 reason 参数）
 * 以及 mark-paid 操作（可选 transaction_ref 参数）
 */
class SettlementReviewRequest extends FormRequest
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
        $action = $this->route()?->getActionMethod();

        return match ($action) {
            'reject', 'cancel' => [
                'reason' => 'required|string|max:500',
            ],
            'markPaid' => [
                'transaction_ref' => 'nullable|string|max:128',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required'         => '原因为必填项',
            'reason.string'           => '原因必须为字符串',
            'reason.max'              => '原因最多不超过 500 个字符',
            'transaction_ref.string'  => '交易流水号必须为字符串',
            'transaction_ref.max'     => '交易流水号最多不超过 128 个字符',
        ];
    }
}
