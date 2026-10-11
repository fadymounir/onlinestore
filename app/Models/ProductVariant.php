<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'price', 'compare_at_price', 'cost_price',
        'stock_quantity', 'low_stock_threshold', 'image', 'is_active',
    ];

    protected $attributes = [
        'stock_quantity' => 0,
        'low_stock_threshold' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The combination that defines this variant, e.g. أحمر + XL. */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    /**
     * Human label such as "أحمر / XL".
     * Deliberately a method, not an Eloquent accessor: an `Attribute` return
     * type here would collide with the App\Models\Attribute model.
     */
    public function label(): string
    {
        return $this->attributeValues->pluck('value')->implode(' / ');
    }

    /**
     * Named isInStock() rather than inStock(): a non-static method of that
     * name would shadow scopeInStock() on static calls.
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold > 0
            && $this->stock_quantity > 0
            && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }
}
