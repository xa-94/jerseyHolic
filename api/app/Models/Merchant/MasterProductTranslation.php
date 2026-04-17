<?php

declare(strict_types=1);

namespace App\Models\Merchant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品多语言翻译模型 — Merchant DB
 *
 * 对应表：master_product_translations（商户独立库，无 jh_ 前缀）
 * 存储主商品的多语言翻译信息，支持 16 种语言。
 *
 * @property int         $id
 * @property int         $master_product_id
 * @property string      $locale
 * @property string      $name
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MasterProductTranslation extends MerchantModel
{
    protected $table = 'master_product_translations';

    protected $fillable = [
        'master_product_id',
        'locale',
        'name',
        'description',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'master_product_id' => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    /**
     * 所属主商品
     */
    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }
}
