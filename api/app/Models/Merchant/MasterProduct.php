<?php

declare(strict_types=1);

namespace App\Models\Merchant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商户主商品模型 — Merchant DB
 *
 * 对应表：master_products（商户独立库，无 jh_ 前缀）
 * 代表商户在"主商品库"中维护的核心商品信息。
 * 可通过 SyncRule 同步到各个 Store（Tenant DB）的 products 表。
 *
 * @property int         $id
 * @property string      $sku
 * @property string      $name
 * @property string|null $description
 * @property int|null    $category_l1_id
 * @property int|null    $category_l2_id
 * @property bool        $is_sensitive
 * @property string      $base_price
 * @property string      $currency
 * @property array|null  $images
 * @property array|null  $attributes
 * @property array|null  $variants
 * @property string|null $weight
 * @property array|null  $dimensions
 * @property int         $status
 * @property string      $sync_status
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MasterProduct extends MerchantModel
{
    protected $table = 'master_products';

    /* ----------------------------------------------------------------
     |  常量
     | ---------------------------------------------------------------- */

    /** @var int 状态：启用 */
    public const STATUS_ACTIVE = 1;

    /** @var int 状态：禁用 */
    public const STATUS_INACTIVE = 0;

    /** @var int 状态：草稿 */
    public const STATUS_DRAFT = 2;

    /** @var string 同步状态：待同步 */
    public const SYNC_PENDING = 'pending';

    /** @var string 同步状态：同步中 */
    public const SYNC_SYNCING = 'syncing';

    /** @var string 同步状态：已同步 */
    public const SYNC_SYNCED = 'synced';

    /** @var string 同步状态：同步失败 */
    public const SYNC_FAILED = 'failed';

    /* ----------------------------------------------------------------
     |  属性
     | ---------------------------------------------------------------- */

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_l1_id',
        'category_l2_id',
        'is_sensitive',
        'base_price',
        'currency',
        'images',
        'attributes',
        'variants',
        'weight',
        'dimensions',
        'status',
        'sync_status',
        'last_synced_at',
    ];

    protected $casts = [
        'images'         => 'json',
        'attributes'     => 'json',
        'variants'       => 'json',
        'dimensions'     => 'json',
        'is_sensitive'   => 'boolean',
        'base_price'     => 'decimal:2',
        'weight'         => 'decimal:2',
        'category_l1_id' => 'integer',
        'category_l2_id' => 'integer',
        'status'         => 'integer',
        'last_synced_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 商品的多语言翻译
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MasterProductTranslation::class, 'master_product_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    /**
     * 筛选启用状态的商品
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * 筛选特货商品
     */
    public function scopeSensitive(Builder $query): Builder
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * 筛选需要同步的商品（pending 或 failed）
     */
    public function scopeNeedSync(Builder $query): Builder
    {
        return $query->whereIn('sync_status', [self::SYNC_PENDING, self::SYNC_FAILED]);
    }

    /* ----------------------------------------------------------------
     |  辅助方法
     | ---------------------------------------------------------------- */

    /**
     * 获取指定语言的翻译记录
     */
    public function getTranslation(string $locale): ?MasterProductTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    /**
     * 获取本地化的商品名称，若无翻译则回退到默认名称
     */
    public function getLocalizedName(string $locale = 'en'): string
    {
        $translation = $this->getTranslation($locale);

        return $translation?->name ?? $this->name;
    }

    /**
     * 获取本地化的商品描述，若无翻译则回退到默认描述
     */
    public function getLocalizedDescription(string $locale = 'en'): ?string
    {
        $translation = $this->getTranslation($locale);

        return $translation?->description ?? $this->description;
    }

    /**
     * 判断商品是否处于启用状态
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 判断商品是否需要同步
     */
    public function needsSync(): bool
    {
        return in_array($this->sync_status, [self::SYNC_PENDING, self::SYNC_FAILED], true);
    }

    /**
     * 将同步状态标记为 pending（修改后触发重新同步）
     */
    public function markForSync(): void
    {
        $this->update(['sync_status' => self::SYNC_PENDING]);
    }
}
