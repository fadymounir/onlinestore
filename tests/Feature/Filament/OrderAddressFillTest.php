<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Admin;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filling in the address of an order that arrived without one, and turning
 * the governorate into a shipping charge.
 */
class OrderAddressFillTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');

        $zone = ShippingZone::create([
            'name' => 'القاهرة الكبرى',
            'cost' => 40,
            'free_shipping_threshold' => 5000,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
        ]);
        $this->governorate = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);
        City::create(['governorate_id' => $this->governorate->id, 'name' => 'مدينة نصر']);

        $customer = User::create([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona@example.com',
            'phone' => '01011234501',
            'password' => 'password',
        ]);

        // As CheckoutService writes it when addresses are switched off.
        $this->order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $customer->id,
            'subtotal' => 480,
            'discount_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 480,
            'recipient_name' => $customer->name,
            'phone' => $customer->phone,
            'placed_at' => now(),
        ]);
    }

    public function test_the_edit_page_opens_and_offers_the_repricing_action(): void
    {
        $this->assertTrue($this->order->lacksAddress());

        Livewire::test(EditOrder::class, ['record' => $this->order->getKey()])
            ->assertOk()
            ->assertSee('هذا الطلب وصل بدون عنوان')
            ->assertActionVisible('repriceShipping');
    }

    public function test_the_action_prices_the_shipping_once_the_governorate_is_set(): void
    {
        $this->order->update(['governorate_id' => $this->governorate->id]);

        Livewire::test(EditOrder::class, ['record' => $this->order->getKey()])
            ->callAction('repriceShipping');

        $this->order->refresh();

        $this->assertSame(40.0, (float) $this->order->shipping_total);
        $this->assertSame(520.0, (float) $this->order->grand_total);
    }

    public function test_the_action_refuses_when_the_governorate_has_no_shipping_zone(): void
    {
        $orphan = Governorate::create(['name' => 'الوادي الجديد']);
        $this->order->update(['governorate_id' => $orphan->id]);

        Livewire::test(EditOrder::class, ['record' => $this->order->getKey()])
            ->callAction('repriceShipping');

        $this->order->refresh();

        // Nothing moved, and the admin was told why.
        $this->assertSame(0.0, (float) $this->order->shipping_total);
        $this->assertSame(480.0, (float) $this->order->grand_total);
    }
}
