<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 支付账号 API 资源
 *
 * 用于 Admin 端账号列表和详情输出。
 * 敏感字段（client_secret, access_token）已在 Model $hidden 中排除。
 */
class PaymentAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'account'              => $this->account,
            'email'                => $this->email,
            'client_id'            => $this->client_id,
            'merchant_id_external' => $this->merchant_id_external,
            'pay_method'           => $this->pay_method,
            'category_id'          => $this->category_id,
            'cc_category_id'       => $this->cc_category_id,
            'status'               => (int) $this->status,
            'permission'           => (int) $this->permission,
            'priority'             => (int) $this->priority,

            // 金额字段
            'min_money'            => (string) $this->min_money,
            'max_money'            => (string) $this->max_money,
            'limit_money'          => (string) $this->limit_money,
            'daily_limit_money'    => (string) $this->daily_limit_money,
            'money_total'          => (string) $this->money_total,
            'daily_money_total'    => (string) $this->daily_money_total,
            'max_num'              => (int) $this->max_num,
            'deal_count'           => (int) $this->deal_count,

            // M3 生命周期 & 健康度
            'lifecycle_stage'      => $this->lifecycle_stage,
            'health_score'         => (int) $this->health_score,
            'single_limit'         => (string) $this->single_limit,
            'daily_limit'          => (string) $this->daily_limit,
            'monthly_limit'        => (string) $this->monthly_limit,
            'daily_count_limit'    => (int) $this->daily_count_limit,
            'total_success_count'  => (int) $this->total_success_count,
            'total_fail_count'     => (int) $this->total_fail_count,
            'total_refund_count'   => (int) $this->total_refund_count,
            'total_dispute_count'  => (int) $this->total_dispute_count,
            'last_used_at'         => $this->last_used_at?->toISOString(),
            'cooling_until'        => $this->cooling_until?->toISOString(),

            // URL & 域名
            'domain'               => $this->domain,
            'webhook_id'           => $this->webhook_id,
            'success_url'          => $this->success_url,
            'cancel_url'           => $this->cancel_url,

            // 状态标记
            'is_new'               => (bool) $this->is_new,
            'is_force'             => (bool) $this->is_force,
            'has_error'            => $this->hasError(),
            'error_msg'            => $this->when($this->hasError(), $this->error_msg),
            'error_time'           => $this->when($this->hasError(), $this->error_time?->toISOString()),

            // 关联分组
            'group'                => $this->when(
                $this->relationLoaded('group'),
                fn () => $this->group ? [
                    'id'   => $this->group->id,
                    'name' => $this->group->name,
                    'type' => $this->group->type,
                ] : null
            ),

            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
