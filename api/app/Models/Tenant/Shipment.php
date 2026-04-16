<?php

namespace App\Models\Tenant;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 发货单 — Tenant DB
 *
 * 对应表：jh_shipments
 */
class Shipment extends TenantModel
{
    protected $table = 'jh_shipments';

    protected $fillable = [
        'order_id', 'shipment_no', 'provider_id', 'tracking_number',
        'carrier_name', 'carrier_name_mapped', 'status', 'weight',
        'safe_product_name', 'paypal_uploaded', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'order_id'        => 'integer',
        'provider_id'     => 'integer',
        'status'          => ShipmentStatus::class,
        'weight'          => 'decimal:2',
        'paypal_uploaded' => 'boolean',
        'shipped_at'      => 'datetime',
        'delivered_at'    => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'provider_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(ShipmentTrack::class, 'shipment_id');
    }

    public function scopeShipped($query)
    {
        return $query->whereIn('status', [
            ShipmentStatus::SHIPPED,
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::DELIVERED,
        ]);
    }
}
