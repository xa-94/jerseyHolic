<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * 域名模型 — Central DB (stancl/tenancy Domain Model)
 *
 * 对应表：domains（central 库）
 * 用于 stancl/tenancy 的域名识别机制，将请求域名映射到对应的 Store（Tenant）。
 *
 * @property int    $id
 * @property string $domain
 * @property int    $store_id
 * @property string|null $certificate_status  pending|provisioning|active|failed|dry_run
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Domain extends BaseDomain
{
    protected $fillable = [
        'domain',
        'store_id',
        'certificate_status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'certificate_status' => 'string',
    ];

    /**
     * 证书状态常量
     */
    public const CERT_PENDING      = 'pending';
    public const CERT_PROVISIONING = 'provisioning';
    public const CERT_ACTIVE       = 'active';
    public const CERT_FAILED       = 'failed';

    /**
     * 判断证书是否已签发
     */
    public function hasCertificate(): bool
    {
        return $this->certificate_status === self::CERT_ACTIVE;
    }

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属店铺
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
