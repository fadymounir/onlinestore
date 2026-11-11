<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `ordering_paused`: a softer switch than maintenance mode. The shop stays
 * open to browse and to fill a cart; only placing the order is refused.
 */
class PausedOrderingTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        Setting::put('min_order_total', '0');

        $zone = ShippingZone::create([
            'name' => 'القاهرة الكبرى',
            'cost' => 40,
            'free_shipping_threshold' => 5000,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
        ]);
        $this->governorate = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);
        City::create(['governorate_id' => $this->governorate->id, 'name' => 'مدينة نصر']);

        $product = Product::create([
            'category_id' => Category::create(['name' => 'فساتين', 'slug' => 'dresses'])->id,
            'name' => 'فستان سواريه مطرز',
            'slug' => 'evening-dress',
            'base_price' => 480,
            'published_at' => now()->subDay(),
        ]);

        $this->variant = $product->variants()->create([
            'sku' => 'DRESS-1', 'price' => 480, 'stock_quantity' => 10,
        ]);
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
            'is_default' => true,
        ]);

        return $user;
    }

    public function test_the_shop_stays_browsable_and_the_cart_still_fills_while_orders_are_paused(): void
    {
        Setting::put('ordering_paused', '1');

        $this->get('/')->assertOk();
        $this->get('/products')->assertOk()->assertSee('فستان سواريه مطرز');
        $this->get('/product/evening-dress')->assertOk();

        $user = $this->customer();
        $this->actingAs($user);

        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2])
            ->assertRedirect();

        $this->get('/cart')
            ->assertOk()
            ->assertSee('فستان سواريه مطرز')
            ->assertSee('استقبال الطلبات متوقف مؤقتاً')
            ->assertDontSee(route('checkout'));
    }

    public function test_checkout_is_refused_while_orders_are_paused(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2]);

        Setting::put('ordering_paused', '1');

        $this->get('/checkout')
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->post('/checkout', [
            'address_id' => $user->addresses()->first()->id,
            'payment_method' => PaymentMethod::CashOnDelivery->value,
        ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_works_again_once_orders_resume(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2]);

        Setting::put('ordering_paused', '0');

        $this->get('/checkout')->assertOk();

        $this->post('/checkout', [
            'address_id' => $user->addresses()->first()->id,
            'payment_method' => PaymentMethod::CashOnDelivery->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }
}
