<?php

namespace Tests\Feature\Store;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\EgyptLocationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeProductWithVariants(): Product
    {
        $category = Category::create(['name' => 'تيشيرتات', 'slug' => 't-shirts']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'تيشيرت قطن',
            'slug' => 'cotton-tshirt',
            'base_price' => 250,
        ]);

        $color = Attribute::create(['name' => 'اللون', 'slug' => 'color', 'type' => 'color']);
        $size = Attribute::create(['name' => 'المقاس', 'slug' => 'size', 'type' => 'select']);
        $product->attributes()->attach([$color->id, $size->id]);

        $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'أحمر', 'color_hex' => '#e53935']);
        $xl = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL']);
        $m = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);

        $redXl = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'TSH-RED-XL', 'price' => 300, 'stock_quantity' => 8,
        ]);
        $redXl->attributeValues()->attach([$red->id, $xl->id]);

        $redM = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'TSH-RED-M', 'price' => 280, 'stock_quantity' => 3,
            'low_stock_threshold' => 5,
        ]);
        $redM->attributeValues()->attach([$red->id, $m->id]);

        return $product->refresh();
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'أحمد محمد',
            'email' => 'ahmed@example.test',
            'phone' => '01012345678',
            'password' => 'password',
        ]);
    }

    public function test_a_product_carries_variants_built_from_attribute_combinations(): void
    {
        $product = $this->makeProductWithVariants();

        $this->assertCount(2, $product->variants);
        $this->assertSame(11, $product->totalStock());
        $this->assertTrue($product->isInStock());
        $this->assertEqualsCanonicalizing(['اللون', 'المقاس'], $product->attributes->pluck('name')->all());

        $redXl = $product->variants->firstWhere('sku', 'TSH-RED-XL');
        $this->assertSame('أحمر / XL', $redXl->label());

        $redM = $product->variants->firstWhere('sku', 'TSH-RED-M');
        $this->assertTrue($redM->isLowStock());
        $this->assertFalse($redXl->isLowStock());
    }

    public function test_a_variant_sku_must_be_unique(): void
    {
        $product = $this->makeProductWithVariants();

        $this->expectException(\Illuminate\Database\QueryException::class);

        ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'TSH-RED-XL', 'price' => 10,
        ]);
    }

    public function test_the_cart_prices_itself_from_the_variants(): void
    {
        $product = $this->makeProductWithVariants();
        $user = $this->customer();

        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_variant_id' => $product->variants->firstWhere('sku', 'TSH-RED-XL')->id,
            'quantity' => 2,
        ]);
        $cart->items()->create([
            'product_variant_id' => $product->variants->firstWhere('sku', 'TSH-RED-M')->id,
            'quantity' => 1,
        ]);

        $cart->load('items.variant');

        $this->assertSame(3, $cart->itemCount());
        $this->assertSame(880.0, $cart->subtotal());   // 300*2 + 280
    }

    public function test_a_percent_coupon_respects_its_cap_and_its_rules(): void
    {
        $user = $this->customer();

        $coupon = Coupon::create([
            'code' => 'EID25',
            'type' => CouponType::Percent,
            'value' => 25,
            'min_order_total' => 500,
            'max_discount' => 150,
            'usage_limit_per_user' => 1,
            'expires_at' => now()->addWeek(),
        ]);

        // 25% of 1000 = 250, capped at 150.
        $this->assertSame(150.0, $coupon->discountFor(1000));
        // 25% of 400 = 100, under the cap.
        $this->assertSame(100.0, $coupon->discountFor(400));

        $this->assertTrue($coupon->isValidFor($user, 800));
        $this->assertFalse($coupon->isValidFor($user, 300));   // below min_order_total

        $expired = Coupon::create([
            'code' => 'OLD', 'type' => CouponType::Fixed, 'value' => 50,
            'expires_at' => now()->subDay(),
        ]);
        $this->assertFalse($expired->isValidFor($user, 800));
    }

    public function test_a_fixed_coupon_never_exceeds_the_subtotal(): void
    {
        $coupon = Coupon::create(['code' => 'BIG', 'type' => CouponType::Fixed, 'value' => 500]);

        $this->assertSame(200.0, $coupon->discountFor(200));
    }

    public function test_an_order_snapshots_the_address_and_survives_product_deletion(): void
    {
        $product = $this->makeProductWithVariants();
        $user = $this->customer();

        $zone = ShippingZone::create(['name' => 'القاهرة الكبرى', 'cost' => 40]);
        $gov = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);
        $city = City::create(['governorate_id' => $gov->id, 'name' => 'مدينة نصر']);

        $address = $user->addresses()->create([
            'governorate_id' => $gov->id,
            'city_id' => $city->id,
            'recipient_name' => 'أحمد محمد',
            'phone' => '01012345678',
            'street' => 'شارع عباس العقاد',
            'building' => '12',
            'floor' => '3',
            'apartment' => '7',
            'is_default' => true,
        ]);

        $this->assertStringContainsString('مدينة نصر', $address->fullAddress());
        $this->assertStringContainsString('شقة 7', $address->fullAddress());

        $variant = $product->variants->firstWhere('sku', 'TSH-RED-XL');

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $user->id,
            'subtotal' => 600,
            'shipping_total' => 40,
            'grand_total' => 640,
            'placed_at' => now(),
            ...$address->toOrderSnapshot(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => $variant->label(),
            'sku' => $variant->sku,
            'unit_price' => 300,
            'quantity' => 2,
            'line_total' => 600,
        ]);

        $this->assertStringStartsWith('ORD-'.now()->format('Ymd'), $order->order_number);
        $this->assertSame('القاهرة', $order->governorate_name);
        $this->assertSame('مدينة نصر', $order->city_name);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);

        // Deleting the catalogue entry must not erase order history.
        $product->forceDelete();

        $item = $order->items()->sole();
        $this->assertNull($item->product_id);
        $this->assertNull($item->product_variant_id);
        $this->assertSame('تيشيرت قطن', $item->product_name);
        $this->assertSame('أحمر / XL', $item->variant_label);
        $this->assertSame('TSH-RED-XL', $item->sku);
    }

    public function test_status_transitions_are_recorded_and_cod_is_marked_paid_on_delivery(): void
    {
        $user = $this->customer();
        $zone = ShippingZone::create(['name' => 'زون', 'cost' => 40]);
        $gov = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $user->id,
            'governorate_id' => $gov->id,
            'recipient_name' => 'أحمد',
            'phone' => '01012345678',
            'governorate_name' => 'القاهرة',
            'street' => 'شارع 1',
            'grand_total' => 640,
        ]);

        $admin = Admin::create([
            'name' => 'مدير', 'email' => 'a@a.test', 'password' => 'password',
        ]);

        $order->transitionTo(OrderStatus::Confirmed, 'تم التأكيد هاتفياً', $admin);
        $order->transitionTo(OrderStatus::Shipped, null, $admin);
        $order->transitionTo(OrderStatus::Delivered, 'تم التحصيل', $admin);

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->delivered_at);
        $this->assertTrue($order->status->isFinal());

        $history = $order->statusHistories()->orderBy('id')->get();
        $this->assertCount(3, $history);
        $this->assertSame(OrderStatus::Pending, $history[0]->from_status);
        $this->assertSame(OrderStatus::Confirmed, $history[0]->to_status);
        $this->assertSame('تم التأكيد هاتفياً', $history[0]->note);
        $this->assertSame($admin->id, $history[0]->admin_id);
    }

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $product = $this->makeProductWithVariants();
        $user = $this->customer();
        $admin = Admin::create(['name' => 'مدير', 'email' => 'a@a.test', 'password' => 'password']);

        $review = Review::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5,
            'comment' => 'منتج ممتاز',
        ]);

        $this->assertFalse($review->is_approved);
        $this->assertFalse($review->isVerifiedPurchase());
        $this->assertCount(0, $product->approvedReviews);

        $review->approve($admin);
        $this->assertTrue($review->fresh()->is_approved);
        $this->assertCount(1, $product->fresh()->approvedReviews);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 1]);
    }

    public function test_a_product_sits_in_a_wishlist_only_once(): void
    {
        $product = $this->makeProductWithVariants();
        $user = $this->customer();

        $user->wishlist()->attach($product->id);
        $this->assertCount(1, $user->fresh()->wishlist);
        $this->assertCount(1, $product->fresh()->wishlistedBy);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $user->wishlist()->attach($product->id);
    }

    public function test_the_egypt_seeder_loads_every_governorate_with_a_shipping_zone(): void
    {
        $this->seed(EgyptLocationSeeder::class);

        $this->assertSame(27, Governorate::count());
        $this->assertSame(4, ShippingZone::count());
        $this->assertGreaterThan(250, City::count());

        // No governorate may be left without a delivery price.
        $this->assertSame(0, Governorate::whereNull('shipping_zone_id')->count());

        $cairo = Governorate::where('name', 'القاهرة')->sole();
        $this->assertSame('القاهرة الكبرى', $cairo->shippingZone->name);
        $this->assertSame(0.0, $cairo->shippingZone->costFor(5000));   // free over threshold
        $this->assertSame(40.0, $cairo->shippingZone->costFor(100));
    }

    public function test_the_attribute_seeder_loads_colours_with_hex_codes(): void
    {
        $this->seed(AttributeSeeder::class);

        $color = Attribute::where('slug', 'color')->sole();
        $this->assertCount(10, $color->values);
        $this->assertSame('#e53935', $color->values->firstWhere('value', 'أحمر')->color_hex);

        $size = Attribute::where('slug', 'size')->sole();
        $this->assertCount(7, $size->values);
    }
}
