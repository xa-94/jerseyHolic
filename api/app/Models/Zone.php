<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zone extends Model
{
    protected $table = 'jh_zones';

    protected $fillable = ['country_id', 'name', 'code', 'status'];

    protected $casts = [
        'country_id' => 'integer',
        'status'     => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
