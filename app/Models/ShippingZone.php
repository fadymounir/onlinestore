<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = [
        'name', 'cost', 'free_shipping_threshold',
        'delivery_days_min', 'delivery_days_max', 'is_active',
    ];

    protected $attributes = [
        'cost' => 0,
        'delivery_days_min' => 1,
        'delivery_days_max' => 3,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }

    /** Shipping is free once the order subtotal clears the threshold. */
    public function costFor(float $subtotal): float
    {
        if ($this->free_shipping_threshold !== null && $subtotal >= (float) $this->free_shipping_threshold) {
            return 0.0;
        }

        return (float) $this->cost;
    }
}
