<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    /** Codes stay ASCII so customers can type them; everything shown is Arabic. */
    private const COUPONS = [
        ['EID25', CouponType::Percent, 25, 500, 300, 200, 1],
        ['WELCOME50', CouponType::Fixed, 50, 300, null, null, 1],
        ['SUMMER15', CouponType::Percent, 15, 400, 150, 500, 3],
        ['FREESHIP', CouponType::Fixed, 70, 1000, null, 100, 2],
        ['BLACKFRIDAY', CouponType::Percent, 40, 800, 600, 50, 1],
        ['EXPIRED10', CouponType::Percent, 10, null, null, null, null],
    ];

    /** Where each seeded order ends up, with the note recorded on the way. */
    private const LIFECYCLES = [
        [OrderStatus::Delivered, 'تم التحصيل بالكامل'],
        [OrderStatus::Delivered, 'تم التسليم للعميل'],
        [OrderStatus::Shipped, 'مع مندوب الشحن'],
        [OrderStatus::Processing, 'جاري التجهيز في المخزن'],
        [OrderStatus::Confirmed, 'تم التأكيد هاتفياً'],
        [OrderStatus::Pending, null],
        [OrderStatus::Cancelled, 'العميل غيّر رأيه'],
        [OrderStatus::Returned, 'المقاس غير مناسب للإصبع'],
    ];

    private const REVIEWS = [
        [5, 'خامة ممتازة', 'القطعة وصلت أسرع من المتوقع والخامة أفضل من الصور. هشتري تاني بالتأكيد.'],
        [4, 'جيد جداً', 'المقاس مطابق للجدول والشغل نظيف، بس اللون أغمق شوية من الصورة.'],
        [5, 'يستحق السعر', 'نسبة السعر للجودة ممتازة، والتغليف كان مرتب جداً.'],
        [3, 'مقبول', 'القطعة كويسة لكن التوصيل اتأخر يومين عن الموعد.'],
        [4, 'خفيف ومريح', 'استانلس حقيقي، لبسته في البحر ولا اتأكسد ولا غيّر لونه.'],
        [2, 'لم يعجبني', 'القفل بيفتح لوحده مع الحركة، واضطررت أرجّعه.'],
        [5, 'تعاملت معاهم كتير', 'دايماً نفس المستوى، وخدمة العملاء بترد بسرعة.'],
    ];

    public function run(): void
    {
        $coupons = $this->seedCoupons();
        $admin = Admin::where('email', 'admin@admin.com')->first();

        $customers = User::with('defaultAddress.governorate', 'defaultAddress.city')->get();
        $variants = ProductVariant::with('product', 'attributeValues')->where('is_active', true)->get();

        if ($customers->isEmpty() || $variants->isEmpty()) {
            return;
        }

        $orderIndex = 0;

        foreach ($customers as $c => $customer) {
            $address = $customer->defaultAddress;

            if (! $address) {
                continue;
            }

            // Two to three orders per customer, spread over the last few months.
            foreach (range(1, 2 + ($c % 2)) as $n) {
                [$finalStatus, $note] = self::LIFECYCLES[$orderIndex % count(self::LIFECYCLES)];

                $placedAt = now()->subDays(90 - $orderIndex * 2)->setTime(10 + ($n % 8), 15);

                $chosen = $variants->slice(($orderIndex * 3) % $variants->count(), 1 + ($orderIndex % 3));

                if ($chosen->isEmpty()) {
                    $chosen = $variants->take(2);
                }

                // Give roughly every third order a coupon.
                $coupon = $orderIndex % 3 === 0 ? $coupons[$orderIndex % 5] : null;

                DB::transaction(function () use (
                    $customer, $address, $chosen, $coupon, $finalStatus, $note, $placedAt, $admin
                ): void {
                    $subtotal = 0.0;
                    $lines = [];

                    foreach ($chosen as $variant) {
                        $quantity = 1 + ($variant->id % 2);
                        $lineTotal = (float) $variant->price * $quantity;
                        $subtotal += $lineTotal;

                        $lines[] = [
                            'product_id' => $variant->product_id,
                            'product_variant_id' => $variant->id,
                            'product_name' => $variant->product->name,
                            'variant_label' => $variant->label(),
                            'sku' => $variant->sku,
                            'unit_price' => $variant->price,
                            'quantity' => $quantity,
                            'line_total' => $lineTotal,
                        ];
                    }

                    $discount = $coupon && $coupon->isValidFor($customer, $subtotal)
                        ? $coupon->discountFor($subtotal)
                        : 0.0;

                    $zone = $address->governorate?->shippingZone;
                    $shipping = $zone ? $zone->costFor($subtotal - $discount) : 50.0;

                    $order = Order::create([
                        'order_number' => Order::nextOrderNumber(),
                        'user_id' => $customer->id,
                        'subtotal' => $subtotal,
                        'discount_total' => $discount,
                        'shipping_total' => $shipping,
                        'grand_total' => $subtotal - $discount + $shipping,
                        'coupon_id' => $discount > 0 ? $coupon->id : null,
                        'coupon_code' => $discount > 0 ? $coupon->code : null,
                        'customer_note' => $placedAt->day % 4 === 0
                            ? 'يُرجى الاتصال قبل التوصيل بنصف ساعة.'
                            : null,
                        'placed_at' => $placedAt,
                        'created_at' => $placedAt,
                        'updated_at' => $placedAt,
                        ...$address->toOrderSnapshot(),
                    ]);

                    $order->items()->createMany($lines);

                    if ($discount > 0) {
                        CouponRedemption::create([
                            'coupon_id' => $coupon->id,
                            'user_id' => $customer->id,
                            'order_id' => $order->id,
                            'discount_amount' => $discount,
                        ]);

                        $coupon->increment('used_count');
                    }

                    $this->walkToStatus($order, $finalStatus, $note, $admin);
                });

                $orderIndex++;
            }
        }

        $this->seedReviews();
    }

    /** @return \Illuminate\Support\Collection<int, Coupon> */
    private function seedCoupons()
    {
        $coupons = collect();

        foreach (self::COUPONS as $i => [$code, $type, $value, $minTotal, $maxDiscount, $limit, $perUser]) {
            $expired = $code === 'EXPIRED10';

            $coupons->push(Coupon::updateOrCreate(
                ['code' => $code],
                [
                    'type' => $type,
                    'value' => $value,
                    'min_order_total' => $minTotal,
                    'max_discount' => $maxDiscount,
                    'usage_limit' => $limit,
                    'usage_limit_per_user' => $perUser,
                    'used_count' => 0,
                    'starts_at' => now()->subMonths(2),
                    'expires_at' => $expired ? now()->subWeeks(3) : now()->addMonths(3),
                    'is_active' => ! $expired,
                ],
            ));
        }

        return $coupons;
    }

    /**
     * Moves an order through the real intermediate statuses so
     * order_status_histories reads like an actual audit trail.
     */
    private function walkToStatus(Order $order, OrderStatus $target, ?string $note, ?Admin $admin): void
    {
        $path = match ($target) {
            OrderStatus::Pending => [],
            OrderStatus::Confirmed => [OrderStatus::Confirmed],
            OrderStatus::Processing => [OrderStatus::Confirmed, OrderStatus::Processing],
            OrderStatus::Shipped => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped],
            OrderStatus::Delivered => [
                OrderStatus::Confirmed, OrderStatus::Processing,
                OrderStatus::Shipped, OrderStatus::Delivered,
            ],
            OrderStatus::Cancelled => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Returned => [
                OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped,
                OrderStatus::Delivered, OrderStatus::Returned,
            ],
        };

        foreach ($path as $step) {
            $order->transitionTo($step, $step === $target ? $note : null, $admin);
        }
    }

    /** Only customers who actually received an order may leave a review. */
    private function seedReviews(): void
    {
        $delivered = Order::with('items')
            ->where('status', OrderStatus::Delivered->value)
            ->get();

        $i = 0;

        foreach ($delivered as $order) {
            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $exists = Review::where('product_id', $item->product_id)
                    ->where('user_id', $order->user_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                [$rating, $title, $comment] = self::REVIEWS[$i % count(self::REVIEWS)];

                // Most are published; the odd one waits for moderation.
                $approved = $i % 5 !== 0;

                Review::create([
                    'product_id' => $item->product_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,          // verified purchase
                    'rating' => $rating,
                    'title' => $title,
                    'comment' => $comment,
                    'is_approved' => $approved,
                    'approved_at' => $approved ? $order->delivered_at?->addDays(2) : null,
                    'approved_by' => $approved ? Admin::value('id') : null,
                ]);

                $i++;
            }
        }

        // A couple of reviews with no order attached, to exercise the
        // "not a verified purchase" path.
        $product = Product::first();
        $user = User::latest('id')->first();

        if ($product && $user && ! Review::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            Review::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'rating' => 4,
                'title' => 'سؤال عن المقاس',
                'comment' => 'مقاس 7 يناسب محيط إصبع كام؟ الجدول مش واضح.',
                'is_approved' => false,
            ]);
        }
    }
}
