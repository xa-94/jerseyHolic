<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 物流轨迹 — Tenant DB
 *
 * 对应表：jh_shipment_tracks
 */
class ShipmentTrack extends TenantModel
{
    protected $table = 'jh_shipment_tracks';

    protected $fillable = [
        'shipment_id', 'status', 'description', 'location', 'tracked_at', 'source',
    ];

    protected $casts = [
        'shipment_id' => 'integer',
        'tracked_at'  => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
