<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'short_description',
        'description', 'base_price', 'compare_at_price',
        'is_active', 'is_featured', 'published_at',
    ];

    protected $attributes = [
        'base_price' => 0,
        'is_active' => true,
        'is_featured' => false,
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'total_stock' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /** Which attributes this product varies by (اللون، المقاس). */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withPivot('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function wishlistedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists')->withTimestamps();
    }

    /**
     * Read from the denormalised column rather than summing the variants, so a
     * grid of products costs no extra queries. ProductVariantObserver updates
     * it whenever a variant's stock moves.
     */
    public function totalStock(): int
    {
        return (int) $this->total_stock;
    }

    public function isInStock(): bool
    {
        return $this->totalStock() > 0;
    }

    /**
     * Recomputes the cached stock in one statement. Written through the query
     * builder: no model events, and the product's own updated_at is left alone
     * — selling a unit is not editing a product.
     *
     * @param  list<int>|null  $productIds  null recounts the whole catalogue
     */
    public static function recountStock(?array $productIds = null): void
    {
        if ($productIds === []) {
            return;
        }

        DB::table('products')
            ->when($productIds !== null, fn ($query) => $query->whereIn('id', $productIds))
            ->update([
                'total_stock' => DB::raw(
                    '(select coalesce(sum(stock_quantity), 0) from product_variants'
                    .' where product_variants.product_id = products.id)'
                ),
            ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('total_stock', '>', 0);
    }

    /**
     * What the storefront is allowed to list: live, in date, and in stock.
     *
     * Stock is part of it because the shop only advertises what it can
     * actually send — a card a customer can tap, choose a size on and then be
     * told is gone is worse than one that was never there. It is a listing
     * rule, not a visibility rule: ProductController gates the product page on
     * `is_active` alone, so a link to something that has just sold out still
     * opens and says so rather than turning into a 404 in someone's messages.
     *
     * One scope rather than a condition per query, so the catalogue, the
     * category pages, search, the home rails and the related strip cannot
     * drift apart on what "available" means.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->active()
            ->inStock()
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
