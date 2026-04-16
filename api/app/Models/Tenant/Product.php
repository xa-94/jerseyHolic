<?php

namespace App\Models\Tenant;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 商品模型 — Tenant DB
 *
 * 对应表：jh_products
 *
 * @property int    $id
 * @property string $model
 * @property string $sku
 * @property string $sku_prefix
 * @property float  $price
 * @property float  $cost_price
 * @property float|null $special_price
 * @property \Carbon\Carbon|null $special_start_at
 * @property \Carbon\Carbon|null $special_end_at
 * @property int    $quantity
 * @property int    $stock_status
 * @property int    $subtract_stock
 * @property float  $weight
 * @property float  $length
 * @property float  $width
 * @property float  $height
 * @property string|null $image
 * @property int    $minimum
 * @property int    $sort_order
 * @property int    $status
 * @property int    $is_featured
 * @property int    $requires_shipping
 * @property int    $merchant_id
 * @property int    $viewed
 * @property int    $sold
 * @property string $upc
 * @property string $ean
 * @property string $isbn
 * @property string $mpn
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Product extends TenantModel
{
    use SoftDeletes;

    protected $table = 'jh_products';

    protected $fillable = [
        'model', 'sku', 'sku_prefix', 'price', 'cost_price',
        'special_price', 'special_start_at', 'special_end_at',
        'quantity', 'stock_status', 'subtract_stock',
        'weight', 'length', 'width', 'height',
        'image', 'minimum', 'sort_order', 'status',
        'is_featured', 'requires_shipping', 'merchant_id',
        'viewed', 'sold', 'upc', 'ean', 'isbn', 'mpn',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'special_price'    => 'decimal:2',
        'special_start_at' => 'datetime',
        'special_end_at'   => 'datetime',
        'quantity'         => 'integer',
        'stock_status'     => 'integer',
        'subtract_stock'   => 'integer',
        'weight'           => 'decimal:2',
        'length'           => 'decimal:2',
        'width'            => 'decimal:2',
        'height'           => 'decimal:2',
        'minimum'          => 'integer',
        'sort_order'       => 'integer',
        'status'           => ProductStatus::class,
        'is_featured'      => 'integer',
        'requires_shipping' => 'integer',
        'merchant_id'      => 'integer',
        'viewed'           => 'integer',
        'sold'             => 'integer',
    ];

    /* ----------------------------------------------------------------
     |  关系
     | ---------------------------------------------------------------- */

    public function descriptions(): HasMany
    {
        return $this->hasMany(ProductDescription::class, 'product_id');
    }

    public function description(): HasOne
    {
        return $this->hasOne(ProductDescription::class, 'product_id')->where('locale', 'en');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSku::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'product_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'jh_product_categories', 'product_id', 'category_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function safeMapping(): HasOne
    {
        return $this->hasOne(ProductSafeMapping::class, 'product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    /* ----------------------------------------------------------------
     |  Scopes
     | ---------------------------------------------------------------- */

    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::ENABLED);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', 1);
    }

    public function scopeBySkuPrefix($query, string $prefix)
    {
        return $query->where('sku_prefix', $prefix);
    }

    public function scopeCounterfeit($query)
    {
        return $query->where('sku_prefix', 'hic');
    }

    /* ----------------------------------------------------------------
     |  访问器
     | ---------------------------------------------------------------- */

    public function getEffectivePriceAttribute(): string
    {
        if ($this->special_price
            && $this->special_start_at <= now()
            && ($this->special_end_at === null || $this->special_end_at >= now())
        ) {
            return $this->special_price;
        }
        return $this->price;
    }
}
