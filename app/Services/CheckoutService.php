<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutService
{
    public function __construct(private readonly CartService $cart) {}

    /** @return array{subtotal: float, discount: float, shipping: float, total: float, coupon: ?Coupon} */
    public function totals(User $user, ?Address $address): array
    {
        $subtotal = $this->cart->subtotal();
        $coupon = $this->resolveCoupon($user, $subtotal);
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0.0;

        $zone = $address?->governorate?->shippingZone;
        $shipping = $zone ? $zone->costFor($subtotal - $discount) : 0.0;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shipping, 2),
            'total' => round($subtotal - $discount + $shipping, 2),
            'coupon' => $coupon,
        ];
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
     */
    public function place(User $user, Address $address, ?string $note = null): Order
    {
        $lines = $this->cart->items();

        if ($lines->isEmpty()) {
            throw new RuntimeException('السلة فارغة.');
        }

        return DB::transaction(function () use ($user, $address, $note, $lines): Order {
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

            $order = Order::create([
                'order_number' => Order::nextOrderNumber(),
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'grand_total' => (float) $subtotal - $discount + $shipping,
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
