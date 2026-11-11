<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Order extends Model
{
    /** Where transfer receipts live; see App\Services\CheckoutService. */
    public const RECEIPT_DISK = 'public';

    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_method', 'payment_status',
        'deposit_status', 'deposit_percent', 'deposit_amount', 'deposit_receipt_path',
        'deposit_reference', 'deposit_rejection_reason', 'deposit_submitted_at',
        'deposit_reviewed_at', 'deposit_reviewed_by',
        'refund_amount', 'refund_reference', 'refund_note', 'refunded_at', 'refunded_by',
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
            'deposit_status' => DepositStatus::class,
            'deposit_percent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_submitted_at' => 'datetime',
            'deposit_reviewed_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
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

    public function depositReviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'deposit_reviewed_by');
    }

    public function refundIssuer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'refunded_by');
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

    /**
     * Records the transition so the admin can see who changed what, when.
     *
     * @throws RuntimeException when a deposit order is confirmed before anyone
     *                          has checked the transfer receipt.
     */
    public function transitionTo(OrderStatus $status, ?string $note = null, ?Admin $admin = null): void
    {
        if ($status === OrderStatus::Confirmed && $this->isDeposit() && ! $this->depositApproved()) {
            throw new RuntimeException('لا يمكن تأكيد الطلب قبل اعتماد إيصال تحويل العربون.');
        }

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
            // Cash on delivery, or the remainder of a deposit order: either
            // way the courier has now collected everything that was due.
            $this->payment_status = PaymentStatus::Paid;
        }

        if ($status === OrderStatus::Cancelled) {
            $this->cancelled_at = now();
            $this->cancel_reason = $note;
        }

        $this->save();
    }

    /*
    |-----------------------------------------------------------------------
    | Deposit (partial prepayment by bank transfer)
    |-----------------------------------------------------------------------
    | The figures live on the order, not in the settings: the percentage can
    | change tomorrow without rewriting what this customer was asked to pay.
    */

    public function isDeposit(): bool
    {
        return $this->payment_method === PaymentMethod::Deposit;
    }

    public function depositApproved(): bool
    {
        return $this->deposit_status === DepositStatus::Approved;
    }

    public function awaitingDepositReview(): bool
    {
        return $this->deposit_status === DepositStatus::Submitted;
    }

    public function depositRejected(): bool
    {
        return $this->deposit_status === DepositStatus::Rejected;
    }

    /** What was asked for up front. Zero for a cash-on-delivery order. */
    public function depositAmount(): float
    {
        return $this->isDeposit() ? round((float) $this->deposit_amount, 2) : 0.0;
    }

    /**
     * The customer chose to transfer the whole order up front rather than the
     * minimum share, so there is nothing for the courier to collect.
     */
    public function depositCoversTotal(): bool
    {
        return $this->isDeposit()
            && $this->depositAmount() >= round((float) $this->grand_total, 2);
    }

    /**
     * Whether this order was placed without a delivery address.
     *
     * Orders taken while `customer_addresses_enabled` is off carry the
     * customer's name and phone but nowhere to send the parcel — the shop
     * rings for that and fills it in here.
     */
    public function lacksAddress(): bool
    {
        return blank($this->street) || blank($this->governorate_name);
    }

    /**
     * What the courier would cost for the governorate now on this order,
     * priced off the order's own figures rather than today's cart. Null when
     * no governorate is set, or when it has no shipping zone to price from.
     */
    public function shippingQuote(): ?float
    {
        $zone = $this->governorate?->shippingZone;

        if (! $zone) {
            return null;
        }

        return $zone->costFor(round((float) $this->subtotal - (float) $this->discount_total, 2));
    }

    /**
     * Prices the shipping on an order that was placed without one, and moves
     * the grand total with it.
     *
     * Only the shipping line moves: the subtotal and the discount were agreed
     * when the order was placed and are not rewritten here. Returns false when
     * there is nothing to price from.
     */
    public function repriceShipping(): bool
    {
        $shipping = $this->shippingQuote();

        if ($shipping === null) {
            return false;
        }

        $this->forceFill([
            'shipping_total' => $shipping,
            'grand_total' => round(
                (float) $this->subtotal - (float) $this->discount_total + $shipping,
                2,
            ),
        ])->save();

        return true;
    }

    /** Cancelled or returned: nothing further will ever be collected on it. */
    public function isVoided(): bool
    {
        return in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Returned], true);
    }

    /** What the store is holding of the customer's money right now. */
    public function paidAmount(): float
    {
        // A refund sent the money back out again, whatever had come in.
        if ($this->isRefunded()) {
            return 0.0;
        }

        if ($this->payment_status === PaymentStatus::Paid) {
            return round((float) $this->grand_total, 2);
        }

        return $this->depositApproved() ? $this->depositAmount() : 0.0;
    }

    /** Still to collect — what the courier carries the order out with. */
    public function remainingAmount(): float
    {
        // A cancelled or returned order is not going out for delivery, so
        // there is nothing left to collect on it — only, perhaps, to give back.
        if ($this->isVoided()) {
            return 0.0;
        }

        return round(max(0, (float) $this->grand_total - $this->paidAmount()), 2);
    }

    /**
     * The line the courier and the customer both read on delivery:
     * "الإجمالي 1000.00 · عربون مدفوع 200.00 · المتبقي 800.00".
     */
    public function deliveryNote(): string
    {
        $money = fn (float $amount): string => number_format($amount, 2).' ج.م';

        if (! $this->isDeposit()) {
            return 'المطلوب تحصيله عند الاستلام '.$money((float) $this->grand_total);
        }

        if ($this->depositCoversTotal()) {
            return 'الإجمالي '.$money((float) $this->grand_total)
                .' · '.($this->depositApproved() ? 'مدفوع بالكامل مقدماً' : 'تحويل كامل القيمة بانتظار الاعتماد')
                .' · لا شيء للتحصيل عند الاستلام';
        }

        return 'الإجمالي '.$money((float) $this->grand_total)
            .' · عربون مدفوع '.$money($this->depositApproved() ? $this->depositAmount() : 0.0)
            .' · المتبقي عند الاستلام '.$money($this->remainingAmount());
    }

    public function depositReceiptUrl(): ?string
    {
        return $this->deposit_receipt_path
            ? Storage::disk(self::RECEIPT_DISK)->url($this->deposit_receipt_path)
            : null;
    }

    /**
     * Files a (re)submitted transfer receipt. Replacing one deletes the file it
     * supersedes, and clears the previous rejection so the admin queue shows
     * one pending receipt per order, never a stale verdict beside a new file.
     */
    public function attachDepositReceipt(string $path, ?string $reference = null, ?float $amount = null): void
    {
        $previous = $this->deposit_receipt_path;
        $total = round((float) $this->grand_total, 2);

        $this->forceFill([
            'deposit_receipt_path' => $path,
            'deposit_reference' => $reference,
            // A replacement transfer may be for a different figure — the first
            // one having been rejected for exactly that reason.
            'deposit_amount' => $amount ?? $this->deposit_amount,
            'deposit_percent' => $amount !== null && $total > 0
                ? round($amount / $total * 100, 2)
                : $this->deposit_percent,
            'deposit_status' => DepositStatus::Submitted,
            'deposit_rejection_reason' => null,
            'deposit_submitted_at' => now(),
            'deposit_reviewed_at' => null,
            'deposit_reviewed_by' => null,
        ])->save();

        if ($previous && $previous !== $path) {
            Storage::disk(self::RECEIPT_DISK)->delete($previous);
        }
    }

    /**
     * Admin accepted the transfer: the order is now part-paid — or paid
     * outright, when the customer transferred the whole total up front.
     */
    public function approveDeposit(?Admin $admin = null): void
    {
        $this->forceFill([
            'deposit_status' => DepositStatus::Approved,
            'deposit_rejection_reason' => null,
            'deposit_reviewed_at' => now(),
            'deposit_reviewed_by' => $admin?->id,
            'payment_status' => $this->depositCoversTotal()
                ? PaymentStatus::Paid
                : PaymentStatus::PartiallyPaid,
        ])->save();
    }

    /** Admin could not match the transfer; the customer may upload another. */
    public function rejectDeposit(string $reason, ?Admin $admin = null): void
    {
        $this->forceFill([
            'deposit_status' => DepositStatus::Rejected,
            'deposit_rejection_reason' => $reason,
            'deposit_reviewed_at' => now(),
            'deposit_reviewed_by' => $admin?->id,
            'payment_status' => PaymentStatus::Pending,
        ])->save();
    }

    /** A customer may replace the receipt until it has been accepted. */
    public function canReplaceDepositReceipt(): bool
    {
        return $this->isDeposit()
            && ! $this->depositApproved()
            && ! $this->isVoided();
    }

    /*
    |-----------------------------------------------------------------------
    | Refunds
    |-----------------------------------------------------------------------
    | Cancelling an order the customer has already part-paid, or returning one
    | the courier collected for, leaves money with the store that is no longer
    | its own. That debt is derived from the order's own state — there is no
    | "refund pending" flag to forget to set — and settled by recording the
    | transfer that pays it back.
    */

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    /**
     * What the store owes back: whatever it was holding when the order was
     * called off. Zero on a live order (nothing is owed while it is still
     * on its way) and zero again once a refund has been recorded.
     */
    public function refundableAmount(): float
    {
        if ($this->isRefunded() || ! $this->isVoided()) {
            return 0.0;
        }

        return $this->paidAmount();
    }

    public function awaitingRefund(): bool
    {
        return $this->refundableAmount() > 0;
    }

    /** The refund that was actually sent, once one has been. */
    public function refundedAmount(): float
    {
        return $this->isRefunded() ? round((float) $this->refund_amount, 2) : 0.0;
    }

    /**
     * Files the transfer that gave the money back. The amount may be less than
     * what is owed — a restocking cut, or a partial settlement — but never more,
     * and never on an order that owes nothing.
     *
     * @throws RuntimeException when nothing is owed, or the figure exceeds it.
     */
    public function recordRefund(
        float $amount,
        ?string $reference = null,
        ?string $note = null,
        ?Admin $admin = null,
    ): void {
        $owed = $this->refundableAmount();

        if ($owed <= 0) {
            throw new RuntimeException('لا يوجد مبلغ مستحق الاسترداد على هذا الطلب.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > $owed) {
            throw new RuntimeException('مبلغ الاسترداد يجب أن يكون بين 0.01 و '.number_format($owed, 2).' ج.م.');
        }

        $this->forceFill([
            'refund_amount' => $amount,
            'refund_reference' => $reference,
            'refund_note' => $note,
            'refunded_at' => now(),
            'refunded_by' => $admin?->id,
            'payment_status' => PaymentStatus::Refunded,
        ])->save();
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

    /**
     * Deposits the admin still has to look at. Named apart from the
     * awaitingDepositReview() predicate above on purpose: a scope sharing a
     * real method's name can never be reached through the magic call.
     */
    public function scopeDepositAwaitingReview(Builder $query): Builder
    {
        return $query->where('deposit_status', DepositStatus::Submitted->value);
    }

    /**
     * Orders that owe the customer money back. The SQL mirrors
     * refundableAmount(): called off, something was paid, nothing refunded yet
     * — `partial` is only ever reached through an approved deposit.
     *
     * Named apart from the awaitingRefund() predicate for the same reason
     * scopeDepositAwaitingReview() is: PHP resolves Order::awaitingRefund()
     * to the real method and never reaches the scope.
     */
    public function scopeOwingRefund(Builder $query): Builder
    {
        return $query->whereNull('refunded_at')
            ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])
            ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyPaid->value]);
    }
}
