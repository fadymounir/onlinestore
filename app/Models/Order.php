<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_method', 'payment_status',
        'subtotal', 'discount_total', 'shipping_total', 'grand_total',
        'coupon_id', 'coupon_code', 'governorate_id',
        'recipient_name', 'phone', 'alternate_phone', 'governorate_name',
        'city_name', 'street', 'building', 'floor', 'apartment', 'landmark',
        'customer_note', 'admin_note', 'cancel_reason',
        'placed_at', 'delivered_at', 'cancelled_at', 'stock_deducted',
    ];

    /** Mirrors the column defaults so a fresh instance is never half-null. */
    protected $attributes = [
        'status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'subtotal' => 0,
        'discount_total' => 0,
        'shipping_total' => 0,
        'grand_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'stock_deducted' => 'boolean',
            'placed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    /** Sequential, human-quotable reference: ORD-20261007-0001 */
    public static function nextOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd');

        $todayCount = static::where('order_number', 'like', $prefix.'%')->count();

        return $prefix.'-'.str_pad((string) ($todayCount + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Records the transition so the admin can see who changed what, when. */
    public function transitionTo(OrderStatus $status, ?string $note = null, ?Admin $admin = null): void
    {
        $from = $this->status;

        $this->statusHistories()->create([
            'from_status' => $from?->value,
            'to_status' => $status->value,
            'note' => $note,
            'admin_id' => $admin?->id,
        ]);

        $this->status = $status;

        if ($status === OrderStatus::Delivered) {
            $this->delivered_at = now();
            $this->payment_status = PaymentStatus::Paid;   // COD collected on delivery
        }

        if ($status === OrderStatus::Cancelled) {
            $this->cancelled_at = now();
            $this->cancel_reason = $note;
        }

        $this->save();
    }

    /**
     * Puts the ordered quantities back on the shelf. Only acts when checkout
     * actually took them, so cancelling an order whose stock was never
     * deducted cannot invent inventory.
     */
    public function restoreStock(): bool
    {
        if (! $this->stock_deducted) {
            return false;
        }

        foreach ($this->items as $item) {
            $item->variant?->increment('stock_quantity', $item->quantity);
        }

        $this->forceFill(['stock_deducted' => false])->save();

        return true;
    }

    public function scopeStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
