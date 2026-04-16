<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 分类多语言描述模型 — Tenant DB
 *
 * 对应表：jh_category_descriptions
 *
 * @property int    $id
 * @property int    $category_id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_keywords
 * @property string $slug
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CategoryDescription extends TenantModel
{
    protected $table = 'jh_category_descriptions';

    protected $fillable = [
        'category_id', 'locale', 'name', 'description',
        'meta_title', 'meta_description', 'meta_keywords', 'slug',
    ];

    protected $casts = [
        'category_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
