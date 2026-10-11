<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    /** Keeps fixtures unique when a test builds more than one order. */
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@admin.com')->sole();
        $this->actingAs($this->admin, 'admin');
    }

    private function makeOrder(OrderStatus $status = OrderStatus::Pending): Order
    {
        $n = ++$this->seq;

        $zone = ShippingZone::firstOrCreate(['name' => 'القاهرة الكبرى'], ['cost' => 40]);
        $gov = Governorate::firstOrCreate(['name' => 'القاهرة'], ['shipping_zone_id' => $zone->id]);

        $user = User::create([
            'name' => 'منى عبد الرحمن',
            'email' => "mona{$n}@example.com",
            'phone' => '0101123450'.$n,
            'password' => 'password',
        ]);

        $product = Product::create([
            'category_id' => Category::firstOrCreate(['slug' => 'dresses'], ['name' => 'فساتين'])->id,
            'name' => 'فستان سواريه',
            'slug' => "dress-{$n}",
            'base_price' => 1850,
        ]);
        $variant = $product->variants()->create(['sku' => "D-{$n}", 'price' => 1850, 'stock_quantity' => 5]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $user->id,
            'governorate_id' => $gov->id,
            'status' => $status,
            'recipient_name' => 'منى عبد الرحمن',
            'phone' => '01011234501',
            'governorate_name' => 'القاهرة',
            'city_name' => 'مدينة نصر',
            'street' => 'شارع عباس العقاد',
            'landmark' => 'أمام سيتي ستارز',
            'subtotal' => 1850,
            'shipping_total' => 40,
            'grand_total' => 1890,
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'أحمر / XL',
            'sku' => $variant->sku,
            'unit_price' => 1850,
            'quantity' => 1,
            'line_total' => 1850,
        ]);

        return $order;
    }

    public function test_the_order_pages_render_with_arabic_detail(): void
    {
        $order = $this->makeOrder();

        $this->get('/admin/orders')->assertOk()->assertSee('الطلبات');

        $this->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('ملخص الطلب')
            ->assertSee('عنوان التوصيل')
            ->assertSee('الإجماليات')
            ->assertSee('سجل الحالات')
            ->assertSee($order->order_number)
            ->assertSee('شارع عباس العقاد')
            ->assertSee('قيد الانتظار');

        $this->get("/admin/orders/{$order->id}/edit")
            ->assertOk()
            ->assertSee('ملاحظة داخلية')
            ->assertSee('تصحيح بيانات التوصيل');
    }

    public function test_orders_cannot_be_created_from_the_panel(): void
    {
        $this->assertFalse(OrderResource::canCreate());
        $this->get('/admin/orders/create')->assertNotFound();
    }

    public function test_only_legal_next_statuses_are_offered(): void
    {
        $pending = $this->makeOrder(OrderStatus::Pending);

        Livewire::test(ViewOrder::class, ['record' => $pending->id])
            ->assertActionExists('moveToconfirmed')
            ->assertActionExists('moveTocancelled')
            // Cannot jump straight to shipped or delivered from pending.
            ->assertActionDoesNotExist('moveToshipped')
            ->assertActionDoesNotExist('moveTodelivered');
    }

    public function test_a_final_order_offers_no_forward_transitions(): void
    {
        $cancelled = $this->makeOrder(OrderStatus::Cancelled);

        Livewire::test(ViewOrder::class, ['record' => $cancelled->id])
            ->assertActionDoesNotExist('moveToconfirmed')
            ->assertActionDoesNotExist('moveTopending')
            ->assertActionDoesNotExist('moveTodelivered');

        $this->assertSame([], OrderStatus::Cancelled->allowedNext());
    }

    public function test_confirming_an_order_records_who_did_it_and_why(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('moveToconfirmed', data: ['note' => 'تم التأكيد هاتفياً']);

        $order->refresh();

        $this->assertSame(OrderStatus::Confirmed, $order->status);

        $history = $order->statusHistories()->orderBy('id')->get();
        $this->assertCount(1, $history);
        $this->assertSame(OrderStatus::Pending, $history[0]->from_status);
        $this->assertSame(OrderStatus::Confirmed, $history[0]->to_status);
        $this->assertSame('تم التأكيد هاتفياً', $history[0]->note);
        $this->assertSame($this->admin->id, $history[0]->admin_id);
    }

    public function test_marking_cash_on_delivery_as_delivered_settles_the_payment(): void
    {
        $order = $this->makeOrder(OrderStatus::Shipped);

        $this->assertSame(PaymentStatus::Pending, $order->payment_status);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('moveTodelivered', data: ['note' => 'تم التحصيل']);

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_a_cancellation_requires_a_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('moveTocancelled', data: ['note' => null])
            ->assertHasActionErrors(['note' => 'required']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_the_delivery_address_is_locked_once_shipped(): void
    {
        $shipped = $this->makeOrder(OrderStatus::Shipped);
        $pending = $this->makeOrder(OrderStatus::Pending);

        $this->assertTrue($shipped->status === OrderStatus::Shipped);
        $this->assertFalse($pending->status->isFinal());

        // Both pages still load; the shipped one renders its fields disabled.
        $this->get("/admin/orders/{$shipped->id}/edit")->assertOk();
        $this->get("/admin/orders/{$pending->id}/edit")->assertOk();
    }

    public function test_the_full_transition_map_is_one_way(): void
    {
        $this->assertSame(
            [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Pending->allowedNext(),
        );
        $this->assertTrue(OrderStatus::Shipped->canMoveTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Delivered->canMoveTo(OrderStatus::Pending));
        $this->assertFalse(OrderStatus::Returned->canMoveTo(OrderStatus::Delivered));
    }
}
