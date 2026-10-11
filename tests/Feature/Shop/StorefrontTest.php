<?php

namespace Tests\Feature\Shop;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttributeSeeder::class);
        $this->seed(SettingSeeder::class);

        $zone = ShippingZone::create([
            'name' => 'القاهرة الكبرى',
            'cost' => 40,
            'free_shipping_threshold' => 1500,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
        ]);
        $this->governorate = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);
        City::create(['governorate_id' => $this->governorate->id, 'name' => 'مدينة نصر']);

        $parent = Category::create(['name' => 'ملابس حريمي', 'slug' => 'women']);
        $category = Category::create(['name' => 'فساتين', 'slug' => 'dresses', 'parent_id' => $parent->id]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'فستان سواريه مطرز',
            'slug' => 'evening-dress',
            'short_description' => 'خامة ممتازة',
            'description' => '<p>وصف</p><script>alert(1)</script>',
            'base_price' => 500,
            'is_featured' => true,
            'published_at' => now()->subDay(),
        ]);

        $color = Attribute::where('slug', 'color')->sole();
        $size = Attribute::where('slug', 'size')->sole();
        $this->product->attributes()->attach([$color->id, $size->id]);

        $red = $color->values()->where('value', 'أحمر')->sole();
        $xl = $size->values()->where('value', 'XL')->sole();

        $this->variant = $this->product->variants()->create([
            'sku' => 'DRESS-RED-XL', 'price' => 500, 'stock_quantity' => 10,
        ]);
        $this->variant->attributeValues()->attach([$red->id, $xl->id]);
    }

    private function customer(): User
    {
        $user = User::create([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona@example.com',
            'phone' => '01011234501',
            'password' => 'password',
        ]);

        $user->addresses()->create([
            'governorate_id' => $this->governorate->id,
            'city_id' => City::first()->id,
            'recipient_name' => 'منى عبد الرحمن',
            'phone' => '01011234501',
            'street' => 'شارع عباس العقاد',
            'building' => '12',
            'is_default' => true,
        ]);

        return $user;
    }

    public function test_the_public_pages_render_in_arabic_rtl(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('lang="ar"', escape: false)
            ->assertSee('dir="rtl"', escape: false)
            ->assertSee('فستان سواريه مطرز')
            ->assertSee('ملابس حريمي');

        $this->get('/products')->assertOk()->assertSee('فستان سواريه مطرز');
        $this->get('/category/women')->assertOk()->assertSee('فستان سواريه مطرز');
        $this->get('/product/evening-dress')->assertOk()->assertSee('اللون')->assertSee('المقاس');
    }

    public function test_a_product_description_is_sanitised_before_being_echoed(): void
    {
        $this->get('/product/evening-dress')
            ->assertOk()
            ->assertSee('<p>وصف</p>', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_an_inactive_product_is_hidden(): void
    {
        $this->product->update(['is_active' => false]);

        $this->get('/product/evening-dress')->assertNotFound();
        $this->get('/products')->assertOk()->assertDontSee('فستان سواريه مطرز');
    }

    public function test_search_and_filters_narrow_the_catalogue(): void
    {
        $this->get('/products?q=فستان')->assertOk()->assertSee('فستان سواريه مطرز');
        $this->get('/products?q=حذاء')->assertOk()->assertDontSee('فستان سواريه مطرز');
        $this->get('/products?min=1000')->assertOk()->assertDontSee('فستان سواريه مطرز');
        $this->get('/products?max=1000')->assertOk()->assertSee('فستان سواريه مطرز');
    }

    public function test_a_guest_can_fill_a_basket_and_it_follows_them_through_login(): void
    {
        $user = $this->customer();

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2])
            ->assertRedirect('/cart');

        $this->get('/cart')->assertOk()->assertSee('فستان سواريه مطرز');

        // Nothing is written to the carts table for a guest.
        $this->assertDatabaseCount('carts', 0);

        $this->post('/login', ['email' => 'mona@example.com', 'password' => 'password'])
            ->assertRedirect();

        // The session basket became a real cart row.
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_the_cart_will_not_exceed_available_stock(): void
    {
        $this->actingAs($this->customer());

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 99]);

        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,   // capped at stock, not 99
        ]);
    }

    public function test_a_sold_out_variant_cannot_be_added(): void
    {
        $this->variant->update(['stock_quantity' => 0]);
        $this->actingAs($this->customer());

        $this->from('/product/evening-dress')
            ->post("/cart/add/{$this->variant->id}")
            ->assertRedirect('/product/evening-dress')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_requires_an_account(): void
    {
        $this->post("/cart/add/{$this->variant->id}");

        $this->get('/checkout')->assertRedirect('/login');
    }

    public function test_placing_an_order_snapshots_it_and_deducts_stock(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2]);

        $this->get('/checkout')->assertOk()->assertSee('الدفع عند الاستلام');

        $response = $this->post('/checkout', [
            'address_id' => $user->addresses()->first()->id,
            'note' => 'اتصل قبل التوصيل',
        ]);

        $order = Order::latest('id')->sole();
        $response->assertRedirect(route('account.orders.show', $order));

        // Totals: 2 x 500 = 1000, under the 1500 free-shipping threshold, +40.
        $this->assertSame('1000.00', $order->subtotal);
        $this->assertSame('40.00', $order->shipping_total);
        $this->assertSame('1040.00', $order->grand_total);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertTrue($order->stock_deducted);

        // Address snapshotted, not referenced.
        $this->assertSame('القاهرة', $order->governorate_name);
        $this->assertSame('مدينة نصر', $order->city_name);
        $this->assertSame('شارع عباس العقاد', $order->street);
        $this->assertSame('اتصل قبل التوصيل', $order->customer_note);

        // Line snapshot.
        $item = $order->items()->sole();
        $this->assertSame('فستان سواريه مطرز', $item->product_name);
        $this->assertSame('DRESS-RED-XL', $item->sku);
        $this->assertStringContainsString('أحمر', $item->variant_label);

        // Stock came off the shelf and the cart was emptied.
        $this->assertSame(8, $this->variant->fresh()->stock_quantity);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_free_shipping_applies_over_the_threshold(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 4]);   // 2000 ج.م

        $this->post('/checkout', ['address_id' => $user->addresses()->first()->id]);

        $order = Order::latest('id')->sole();
        $this->assertSame('2000.00', $order->subtotal);
        $this->assertSame('0.00', $order->shipping_total);
    }

    public function test_a_coupon_discounts_the_order_and_is_recorded(): void
    {
        $user = $this->customer();
        $coupon = Coupon::create([
            'code' => 'EID25',
            'type' => CouponType::Percent,
            'value' => 25,
            'max_discount' => 150,
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($user);
        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2]);

        $this->post('/cart/coupon', ['code' => 'eid25'])->assertSessionHas('success');

        $this->post('/checkout', ['address_id' => $user->addresses()->first()->id]);

        $order = Order::latest('id')->sole();

        // 25% of 1000 = 250, capped at 150. 1000 - 150 = 850, under 1500 -> +40.
        $this->assertSame('150.00', $order->discount_total);
        $this->assertSame('890.00', $order->grand_total);
        $this->assertSame('EID25', $order->coupon_code);

        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
        ]);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_an_invalid_coupon_is_rejected(): void
    {
        $this->actingAs($this->customer());
        $this->post("/cart/add/{$this->variant->id}");

        $this->from('/cart')->post('/cart/coupon', ['code' => 'NOPE'])
            ->assertRedirect('/cart')
            ->assertSessionHas('error');
    }

    public function test_checkout_refuses_when_a_line_went_out_of_stock_meanwhile(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 5]);

        // Someone else bought the stock while this basket sat open.
        $this->variant->update(['stock_quantity' => 1]);

        $this->from('/checkout')
            ->post('/checkout', ['address_id' => $user->addresses()->first()->id])
            ->assertRedirect('/checkout')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $this->variant->fresh()->stock_quantity);
    }

    public function test_a_customer_can_cancel_before_shipping_and_stock_returns(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 3]);
        $this->post('/checkout', ['address_id' => $user->addresses()->first()->id]);

        $order = Order::latest('id')->sole();
        $this->assertSame(7, $this->variant->fresh()->stock_quantity);

        $this->post(route('account.orders.cancel', $order), ['reason' => 'غيّرت رأيي'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertFalse($order->stock_deducted);
        $this->assertSame(10, $this->variant->fresh()->stock_quantity);

        // Cancelling twice must not hand back stock again.
        $order->restoreStock();
        $this->assertSame(10, $this->variant->fresh()->stock_quantity);
    }

    public function test_a_customer_cannot_see_another_customers_order(): void
    {
        $owner = $this->customer();
        $this->actingAs($owner);
        $this->post("/cart/add/{$this->variant->id}");
        $this->post('/checkout', ['address_id' => $owner->addresses()->first()->id]);
        $order = Order::latest('id')->sole();

        $intruder = User::create([
            'name' => 'آخر', 'email' => 'other@example.com',
            'phone' => '01011234599', 'password' => 'password',
        ]);

        $this->actingAs($intruder)->get(route('account.orders.show', $order))->assertNotFound();
        $this->actingAs($intruder)->post(route('account.orders.cancel', $order), ['reason' => 'x'])->assertNotFound();
    }

    public function test_registration_creates_a_customer_and_validates_an_egyptian_phone(): void
    {
        $this->post('/register', [
            'name' => 'سارة عادل',
            'email' => 'sara@example.com',
            'phone' => '0123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('phone');

        $this->post('/register', [
            'name' => 'سارة عادل',
            'email' => 'sara@example.com',
            'phone' => '01112345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'sara@example.com', 'phone' => '01112345678']);
        $this->assertAuthenticated();
    }

    public function test_a_deactivated_customer_cannot_sign_in(): void
    {
        $user = $this->customer();
        $user->update(['is_active' => false]);

        $this->post('/login', ['email' => 'mona@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wishlist_and_reviews_work_for_a_signed_in_customer(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post(route('wishlist.toggle', $this->product))->assertSessionHas('success');
        $this->assertCount(1, $user->fresh()->wishlist);
        $this->get('/wishlist')->assertOk()->assertSee('فستان سواريه مطرز');

        $this->post(route('wishlist.toggle', $this->product));
        $this->assertCount(0, $user->fresh()->wishlist);

        $this->post(route('review.store', $this->product), [
            'rating' => 5,
            'title' => 'خامة ممتازة',
            'comment' => 'وصل بسرعة',
        ])->assertSessionHas('success');

        $review = Review::sole();
        $this->assertFalse($review->is_approved);

        // Unapproved reviews must not appear on the product page.
        $this->get('/product/evening-dress')->assertOk()->assertDontSee('وصل بسرعة');

        $review->update(['is_approved' => true]);
        $this->get('/product/evening-dress')->assertOk()->assertSee('وصل بسرعة');
    }

    public function test_the_minimum_order_setting_blocks_a_too_small_basket(): void
    {
        $this->assertTrue(Setting::put('min_order_total', '2000'));

        $user = $this->customer();
        $this->actingAs($user);
        $this->post("/cart/add/{$this->variant->id}");   // 500 ج.م only

        $this->get('/checkout')->assertRedirect('/cart');

        $this->from('/checkout')
            ->post('/checkout', ['address_id' => $user->addresses()->first()->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);

        // Clearing the minimum lets the same basket through.
        Setting::put('min_order_total', '0');

        $this->post('/checkout', ['address_id' => $user->addresses()->first()->id]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_turning_off_cash_on_delivery_stops_checkout(): void
    {
        $this->assertTrue(Setting::put('cod_enabled', '0'));

        $user = $this->customer();
        $this->actingAs($user);
        $this->post("/cart/add/{$this->variant->id}");

        $this->from('/checkout')
            ->post('/checkout', ['address_id' => $user->addresses()->first()->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_switching_off_moderation_publishes_reviews_immediately(): void
    {
        $this->assertTrue(Setting::put('reviews_need_approval', '0'));

        $this->actingAs($this->customer());

        $this->post(route('review.store', $this->product), ['rating' => 5, 'comment' => 'رائع'])
            ->assertSessionHas('success');

        $review = Review::sole();
        $this->assertTrue($review->is_approved);
        $this->assertNotNull($review->approved_at);

        $this->get('/product/evening-dress')->assertOk()->assertSee('رائع');
    }

    public function test_addresses_can_be_managed_and_only_one_stays_default(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post('/account/addresses', [
            'recipient_name' => 'منى',
            'phone' => '01011234502',
            'governorate_id' => $this->governorate->id,
            'street' => 'شارع العمل',
            'is_default' => '1',
        ])->assertRedirect('/account/addresses');

        $this->assertSame(2, $user->addresses()->count());
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->assertSame('شارع العمل', $user->addresses()->where('is_default', true)->sole()->street);
    }
}
