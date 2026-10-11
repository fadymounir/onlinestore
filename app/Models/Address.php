<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'governorate_id', 'city_id', 'label', 'recipient_name',
        'phone', 'alternate_phone', 'street', 'building', 'floor',
        'apartment', 'landmark', 'is_default',
    ];

    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** One-line Arabic address for order summaries and courier sheets. */
    public function fullAddress(): string
    {
        return collect([
            $this->street,
            $this->building ? "عقار {$this->building}" : null,
            $this->floor ? "الدور {$this->floor}" : null,
            $this->apartment ? "شقة {$this->apartment}" : null,
            $this->city?->name,
            $this->governorate?->name,
        ])->filter()->implode('، ');
    }

    /** Snapshot copied onto an order so later edits never rewrite history. */
    public function toOrderSnapshot(): array
    {
        return [
            'governorate_id' => $this->governorate_id,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'governorate_name' => $this->governorate?->name,
            'city_name' => $this->city?->name,
            'street' => $this->street,
            'building' => $this->building,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
            'landmark' => $this->landmark,
        ];
    }
}
