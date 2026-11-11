<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentMethod;
use App\Http\Middleware\AddressesGate;
use App\Models\Address;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `customer_addresses_enabled`: whether a customer may file a new delivery
 * address. Only adding is gated — an address already on file stays theirs
 * to correct.
 */
class CustomerAddressSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);

        $zone = ShippingZone::create([
            'name' => 'القاهرة الكبرى',
            'cost' => 40,
            'free_shipping_threshold' => 5000,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
        ]);
        $this->governorate = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);
        City::create(['governorate_id' => $this->governorate->id, 'name' => 'مدينة نصر']);

        $this->user = User::create([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona@example.com',
            'phone' => '01011234501',
            'password' => 'password',
        ]);

        $this->actingAs($this->user);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'governorate_id' => $this->governorate->id,
            'city_id' => City::first()->id,
            'recipient_name' => 'منى عبد الرحمن',
            'phone' => '01011234501',
            'street' => 'شارع عباس العقاد',
        ];
    }

    public function test_adding_an_address_is_on_by_default(): void
    {
        // Checkout needs an address, so a shop that could not take one from
        // a new customer could not take their order either.
        $this->assertSame('1', Setting::get('customer_addresses_enabled'));
        $this->assertTrue(Setting::customerAddressesEnabled());

        $this->get('/account/addresses')
            ->assertOk()
            ->assertSee('إضافة عنوان جديد')
            ->assertDontSee('إضافة العناوين متوقفة');

        $this->post('/account/addresses', $this->payload())
            ->assertRedirect(route('account.addresses.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, Address::count());
    }

    public function test_switching_it_off_closes_the_address_book_entirely(): void
    {
        $address = $this->user->addresses()->create([...$this->payload(), 'is_default' => true]);

        Setting::put('customer_addresses_enabled', '0');

        // Every way in redirects away — reading, adding, editing, deleting.
        foreach ([
            ['get', '/account/addresses', []],
            ['get', route('account.addresses.edit', $address), []],
            ['post', '/account/addresses', $this->payload()],
            ['put', route('account.addresses.update', $address), $this->payload()],
            ['delete', route('account.addresses.destroy', $address), []],
        ] as [$verb, $uri, $data]) {
            $this->{$verb}($uri, $data)
                ->assertRedirect(route('account.orders.index'))
                ->assertSessionHas('error', AddressesGate::CLOSED_MESSAGE);
        }

        // Nothing was added, nothing was removed.
        $this->assertSame(1, Address::count());
        $this->assertSame('شارع عباس العقاد', $address->fresh()->street);
    }

    public function test_the_account_menu_and_footer_stop_linking_to_the_address_book(): void
    {
        $this->get('/')->assertOk()->assertSee('عناويني');

        Setting::put('customer_addresses_enabled', '0');

        $this->get('/')->assertOk()->assertDontSee('عناويني');
    }

    public function test_checkout_drops_the_address_step_and_still_places_the_order(): void
    {
        // An address already on file must not drag the step back: the switch,
        // not the customer's history, decides whether checkout asks.
        $this->user->addresses()->create([...$this->payload(), 'is_default' => true]);

        $product = Product::create([
            'category_id' => Category::create(['name' => 'فساتين', 'slug' => 'dresses'])->id,
            'name' => 'فستان سواريه مطرز',
            'slug' => 'evening-dress',
            'base_price' => 480,
            'published_at' => now()->subDay(),
        ]);
        $variant = $product->variants()->create(['sku' => 'DRESS-1', 'price' => 480, 'stock_quantity' => 10]);

        Setting::put('customer_addresses_enabled', '0');
        Setting::put('min_order_total', '0');

        $this->post("/cart/add/{$variant->id}", ['quantity' => 1]);

        $this->get('/checkout')
            ->assertOk()
            ->assertDontSee('عنوان التوصيل')
            ->assertSee('سنتواصل معك على رقم حسابك')
            // Zero shipping with no address is "not priced yet", not "free".
            ->assertSee('يُحسب عند التأكيد')
            ->assertDontSee('مجاني');

        // No address_id at all — the form no longer has that field.
        $this->post('/checkout', ['payment_method' => PaymentMethod::CashOnDelivery->value])
            ->assertSessionHasNoErrors();

        $order = Order::sole();

        // The place is unknown, but the order still says who to ring for it.
        $this->assertNull($order->street);
        $this->assertNull($order->governorate_name);
        $this->assertNull($order->governorate_id);
        $this->assertSame($this->user->name, $order->recipient_name);
        $this->assertSame($this->user->phone, $order->phone);
        $this->assertSame(0.0, (float) $order->shipping_total);
        $this->assertSame(480.0, (float) $order->grand_total);
    }

    public function test_an_admin_can_fill_the_address_in_later_and_price_the_shipping(): void
    {
        $order = $this->placeAddresslessOrder();

        $this->assertTrue($order->lacksAddress());
        $this->assertSame(0.0, (float) $order->shipping_total);
        $this->assertSame(480.0, (float) $order->grand_total);

        // Nothing to price from yet.
        $this->assertNull($order->shippingQuote());
        $this->assertFalse($order->repriceShipping());

        // The shop rings, learns where it goes, and fills it in.
        $order->update([
            'governorate_id' => $this->governorate->id,
            'governorate_name' => $this->governorate->name,
            'city_name' => 'مدينة نصر',
            'street' => 'شارع عباس العقاد',
        ]);

        $order->refresh();
        $this->assertFalse($order->lacksAddress());

        // The address alone does not move money — repricing is the explicit act.
        $this->assertSame(0.0, (float) $order->shipping_total);

        $this->assertSame(40.0, $order->shippingQuote());
        $this->assertTrue($order->repriceShipping());

        $order->refresh();
        $this->assertSame(40.0, (float) $order->shipping_total);
        $this->assertSame(520.0, (float) $order->grand_total);

        // Only the shipping line moved; what was agreed at checkout stands.
        $this->assertSame(480.0, (float) $order->subtotal);
        $this->assertSame(0.0, (float) $order->discount_total);
    }

    public function test_repricing_is_idempotent(): void
    {
        $order = $this->placeAddresslessOrder();
        $order->update(['governorate_id' => $this->governorate->id]);

        $order->refresh()->repriceShipping();
        $first = (float) $order->fresh()->grand_total;

        $order->fresh()->repriceShipping();

        // Shipping is set, not accumulated — pressing twice changes nothing.
        $this->assertSame($first, (float) $order->fresh()->grand_total);
        $this->assertSame(520.0, $first);
    }

    /** A 480 EGP order taken with addresses switched off. */
    private function placeAddresslessOrder(): Order
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'فساتين', 'slug' => 'dresses'])->id,
            'name' => 'فستان سواريه مطرز',
            'slug' => 'evening-dress',
            'base_price' => 480,
            'published_at' => now()->subDay(),
        ]);
        $variant = $product->variants()->create(['sku' => 'DRESS-1', 'price' => 480, 'stock_quantity' => 10]);

        Setting::put('customer_addresses_enabled', '0');
        Setting::put('min_order_total', '0');

        $this->post("/cart/add/{$variant->id}", ['quantity' => 1]);
        $this->post('/checkout', ['payment_method' => PaymentMethod::CashOnDelivery->value])
            ->assertSessionHasNoErrors();

        return Order::sole();
    }

    public function test_addresses_stay_editable_while_the_switch_is_on(): void
    {
        $address = $this->user->addresses()->create([...$this->payload(), 'is_default' => true]);

        $this->assertTrue(Setting::customerAddressesEnabled());

        $this->get(route('account.addresses.edit', $address))
            ->assertOk()
            ->assertSee('تعديل العنوان');

        $this->put(route('account.addresses.update', $address), [...$this->payload(), 'street' => 'شارع مكرم عبيد'])
            ->assertSessionHas('success');

        $this->assertSame('شارع مكرم عبيد', $address->fresh()->street);
    }
}
