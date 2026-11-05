<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutService
{
    /** Used when `deposit_percent` is missing or out of range. */
    public const DEFAULT_DEPOSIT_PERCENT = 20.0;

    /** Where transfer receipts are filed on Order::RECEIPT_DISK. */
    public const RECEIPT_DIRECTORY = 'deposits';

    public function __construct(private readonly CartService $cart) {}

    /**
     * @return array{subtotal: float, discount: float, shipping: float, total: float,
     *               deposit: float, deposit_window: array{int, int},
     *               deposit_percent: float, coupon: ?Coupon}
     */
    public function totals(User $user, ?Address $address): array
    {
        $subtotal = $this->cart->subtotal();
        $coupon = $this->resolveCoupon($user, $subtotal);
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0.0;

        $zone = $address?->governorate?->shippingZone;
        $shipping = $zone ? $zone->costFor($subtotal - $discount) : 0.0;

        $total = round($subtotal - $discount + $shipping, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shipping, 2),
            'total' => $total,
            'deposit' => $this->depositFor($total),
            'deposit_window' => $this->depositWindow($total),
            'deposit_percent' => $this->depositPercent(),
            'coupon' => $coupon,
        ];
    }

    /**
     * The configured deposit share, clamped to something payable. A nonsense
     * setting (0, 500, "abc") falls back to the default rather than asking a
     * customer to transfer nothing — or more than the order is worth.
     */
    public function depositPercent(): float
    {
        $percent = (float) Setting::get('deposit_percent', self::DEFAULT_DEPOSIT_PERCENT);

        if ($percent <= 0 || $percent > 100) {
            $percent = self::DEFAULT_DEPOSIT_PERCENT;
        }

        return round($percent, 2);
    }

    /** What that percentage comes to on this order total — the minimum. */
    public function depositFor(float $total): float
    {
        return round($total * $this->depositPercent() / 100, 2);
    }

    /**
     * The window the customer may transfer in, as whole pounds: from the
     * configured share rounded up, to the whole order rounded down — nobody
     * transfers piastres, and a receipt is easier to match against a round
     * figure. Somebody who would rather settle the lot now may do so.
     *
     * @return array{int, int} minimum, maximum
     */
    public function depositWindow(float $total): array
    {
        $minimum = max(1, (int) ceil($this->depositFor($total)));

        return [$minimum, max($minimum, (int) floor(round($total, 2)))];
    }

    /**
     * The figure the customer typed, or null when it is not one this order
     * accepts — not a whole number, or outside the window — so the caller can
     * say so in its own words. Nothing is assumed for a missing figure: the
     * customer transferred a specific amount and is the one who knows it.
     */
    public function resolveDeposit(float|int|string|null $requested, float $total): ?float
    {
        if ($requested === null || $requested === '' || ! is_numeric($requested)) {
            return null;
        }

        // Whole pounds only: 67.40 is a share, not something anyone transfers.
        if (floor((float) $requested) !== (float) $requested) {
            return null;
        }

        [$minimum, $maximum] = $this->depositWindow($total);
        $amount = (int) $requested;

        return $amount >= $minimum && $amount <= $maximum ? (float) $amount : null;
    }

    /** The message that names the window, for a figure outside it. */
    public function depositRangeMessage(float $total): string
    {
        [$minimum, $maximum] = $this->depositWindow($total);

        return 'اكتب المبلغ المحوَّل بالجنيه الصحيح، من '.number_format($minimum)
            .' إلى '.number_format($maximum).' ج.م.';
    }

    /**
     * Files a transfer receipt and returns its stored path.
     *
     * The name is random rather than derived from the order or the customer:
     * these files sit on the public disk (like every other upload here), so an
     * unguessable path is what keeps one customer's receipt out of reach of
     * anyone who hasn't been handed its URL. The extension comes from the
     * file's own type, never from the name the browser sent.
     */
    public function storeReceipt(UploadedFile $file): string
    {
        return $file->storeAs(
            self::RECEIPT_DIRECTORY,
            Str::random(40).'.'.($file->extension() ?: 'bin'),
            Order::RECEIPT_DISK,
        );
    }

    /** What an up-front amount comes to as a percentage of the order. */
    public function shareOf(float $amount, float $total): float
    {
        return $total > 0 ? round($amount / $total * 100, 2) : 100.0;
    }

    /** The session coupon, but only if it is still valid for this basket. */
    public function resolveCoupon(User $user, float $subtotal): ?Coupon
    {
        $code = $this->cart->couponCode();

        if (blank($code)) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();

        return $coupon && $coupon->isValidFor($user, $subtotal) ? $coupon : null;
    }

    /**
     * Turns the basket into an order inside one transaction: snapshots the
     * address and every line, deducts stock, records the coupon use, empties
     * the cart. Rolls the whole thing back if any line went out of stock.
     *
     * A deposit order also snapshots what the customer transferred up front,
     * the share of the order that comes to, and the transfer receipt — then
     * waits on an admin to accept that receipt.
     */
    public function place(
        User $user,
        Address $address,
        ?string $note = null,
        PaymentMethod $method = PaymentMethod::CashOnDelivery,
        ?string $receiptPath = null,
        ?string $reference = null,
        ?float $depositAmount = null,
    ): Order {
        $lines = $this->cart->items();

        if ($lines->isEmpty()) {
            throw new RuntimeException('السلة فارغة.');
        }

        if ($method->requiresReceipt() && blank($receiptPath)) {
            throw new RuntimeException('أرفق إيصال تحويل العربون لإتمام الطلب.');
        }

        return DB::transaction(function () use (
            $user, $address, $note, $lines, $method, $receiptPath, $reference, $depositAmount
        ): Order {
            // Lock the variant rows so two shoppers can't claim the last piece.
            $variants = ProductVariant::whereIn('id', $lines->pluck('variant.id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->assertStillInStock($lines, $variants);

            $subtotal = $lines->sum(
                fn (array $line): float => (float) $variants[$line['variant']->id]->price * $line['quantity'],
            );

            $coupon = $this->resolveCoupon($user, (float) $subtotal);
            $discount = $coupon ? $coupon->discountFor((float) $subtotal) : 0.0;

            $zone = $address->governorate?->shippingZone;
            $shipping = $zone ? $zone->costFor((float) $subtotal - $discount) : 0.0;

            $grandTotal = round((float) $subtotal - $discount + $shipping, 2);
            $isDeposit = $method === PaymentMethod::Deposit;

            // Re-checked against the total this transaction just computed, not
            // the one the page was rendered with: a price or a coupon may have
            // moved since, and the figure has to fit the order being written.
            $deposit = $isDeposit
                ? $this->resolveDeposit($depositAmount, $grandTotal)
                : null;

            if ($isDeposit && $deposit === null) {
                throw new RuntimeException($this->depositRangeMessage($grandTotal));
            }

            $order = Order::create([
                'order_number' => Order::nextOrderNumber(),
                'user_id' => $user->id,
                'payment_method' => $method,
                'deposit_status' => $isDeposit ? DepositStatus::Submitted : null,
                // The share this order was actually part-paid with, which is
                // the configured percentage only when the customer took the
                // figure as offered.
                'deposit_percent' => $isDeposit ? $this->shareOf($deposit, $grandTotal) : null,
                'deposit_amount' => $deposit,
                'deposit_receipt_path' => $isDeposit ? $receiptPath : null,
                'deposit_reference' => $isDeposit ? $reference : null,
                'deposit_submitted_at' => $isDeposit ? now() : null,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'grand_total' => $grandTotal,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'customer_note' => $note,
                'placed_at' => now(),
                'stock_deducted' => true,
                ...$address->toOrderSnapshot(),
            ]);

            foreach ($lines as $line) {
                /** @var ProductVariant $variant */
                $variant = $variants[$line['variant']->id];
                $quantity = $line['quantity'];

                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_label' => $variant->label(),
                    'sku' => $variant->sku,
                    'unit_price' => $variant->price,
                    'quantity' => $quantity,
                    'line_total' => (float) $variant->price * $quantity,
                ]);

                $variant->decrement('stock_quantity', $quantity);
            }

            if ($coupon && $discount > 0) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_amount' => $discount,
                ]);

                $coupon->increment('used_count');
            }

            $this->cart->clear();

            return $order;
        });
    }

    /**
     * @param  Collection<int, array{variant: ProductVariant, quantity: int}>  $lines
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function assertStillInStock(Collection $lines, Collection $variants): void
    {
        foreach ($lines as $line) {
            $id = $line['variant']->id;

            if (! $variants->has($id) || ! $variants[$id]->is_active) {
                throw new RuntimeException('أحد المنتجات في سلتك لم يعد متاحاً.');
            }

            if ($variants[$id]->stock_quantity < $line['quantity']) {
                throw new RuntimeException(
                    'الكمية المطلوبة من «'.$variants[$id]->product->name.'» غير متاحة. المتاح: '
                    .$variants[$id]->stock_quantity,
                );
            }
        }
    }
}
