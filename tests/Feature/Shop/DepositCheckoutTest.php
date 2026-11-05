<?php

namespace Tests\Feature\Shop;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Deposit checkout: the customer transfers a share of the order up front,
 * attaches the receipt, and pays the rest to the courier.
 */
class DepositCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

    /** A basket of 2 × 480 + 40 shipping = 1000, so 20% is a round 200. */
    private function basket(User $user): void
    {
        $this->actingAs($user);
        $this->post("/cart/add/{$this->variant->id}", ['quantity' => 2]);
    }

    /** The basket below comes to 1,000.00: 2 × 480 plus 40 shipping. */
    private const ORDER_TOTAL = 1000.0;

    private function placeDeposit(User $user, array $overrides = []): TestResponse
    {
        return $this->post('/checkout', [
            'address_id' => $user->addresses()->first()->id,
            'payment_method' => PaymentMethod::Deposit->value,
            'deposit_receipt' => UploadedFile::fake()->image('receipt.jpg'),
            // The smallest whole-pound figure this shop accepts, whatever the
            // configured share is.
            'transfer_amount' => app(CheckoutService::class)->depositWindow(self::ORDER_TOTAL)[0],
            'transfer_reference' => 'TRX-9087',
            ...$overrides,
        ]);
    }

    public function test_checkout_offers_the_deposit_option_with_its_share_and_transfer_details(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('الدفع عند الاستلام')
            ->assertSee('عربون مقدّم بتحويل بنكي')
            ->assertSee('20%')
            ->assertSee('إيصال التحويل')
            // 20% of 1,000, as whole pounds, and the order it is a share of.
            ->assertSee('200 ج.م')
            ->assertSee('1,000.00')
            ->assertSee('إنستاباي / محفظة: 01000000000');
    }

    public function test_placing_a_deposit_order_snapshots_the_figures_and_files_the_receipt(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $response = $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $response->assertRedirect(route('account.orders.show', $order));
        $response->assertSessionHas('success');

        $this->assertSame(PaymentMethod::Deposit, $order->payment_method);
        $this->assertSame('1000.00', $order->grand_total);
        $this->assertSame('20.00', $order->deposit_percent);
        $this->assertSame('200.00', $order->deposit_amount);
        $this->assertSame('TRX-9087', $order->deposit_reference);

        // Waiting on the admin: the order is placed but nothing is paid yet.
        $this->assertSame(DepositStatus::Submitted, $order->deposit_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNotNull($order->deposit_submitted_at);

        Storage::disk('public')->assertExists($order->deposit_receipt_path);
        $this->assertStringStartsWith('deposits/', $order->deposit_receipt_path);
        // Random name, not the one the browser sent.
        $this->assertStringNotContainsString('receipt', $order->deposit_receipt_path);

        // Stock still leaves the shelf: the order is real, only part-paid.
        $this->assertSame(8, $this->variant->fresh()->stock_quantity);
    }

    public function test_the_breakdown_the_courier_reads_is_total_deposit_and_remainder(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();

        // Nothing is counted as paid until the transfer is accepted.
        $this->assertSame(0.0, $order->paidAmount());
        $this->assertSame(1000.0, $order->remainingAmount());

        $order->approveDeposit();

        $this->assertSame(200.0, $order->paidAmount());
        $this->assertSame(800.0, $order->remainingAmount());
        $this->assertSame(
            'الإجمالي 1,000.00 ج.م · عربون مدفوع 200.00 ج.م · المتبقي عند الاستلام 800.00 ج.م',
            $order->deliveryNote(),
        );

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('العربون المقدّم')
            ->assertSee('المتبقي عند الاستلام')
            ->assertSee('1,000.00')
            ->assertSee('200.00')
            ->assertSee('800.00');
    }

    public function test_a_deposit_order_without_a_receipt_is_refused(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->from('/checkout')
            ->post('/checkout', [
                'address_id' => $user->addresses()->first()->id,
                'payment_method' => PaymentMethod::Deposit->value,
            ])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('deposit_receipt');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_oversized_or_wrongly_typed_receipt_is_refused(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->from('/checkout')
            ->post('/checkout', [
                'address_id' => $user->addresses()->first()->id,
                'payment_method' => PaymentMethod::Deposit->value,
                'deposit_receipt' => UploadedFile::fake()->create('receipt.exe', 20, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('deposit_receipt');

        $this->assertDatabaseCount('orders', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles('deposits'));
    }

    public function test_turning_the_deposit_off_hides_it_and_refuses_it(): void
    {
        $this->assertTrue(Setting::put('deposit_enabled', '0'));

        $user = $this->customer();
        $this->basket($user);

        $this->get('/checkout')->assertOk()->assertDontSee('عربون مقدّم بتحويل بنكي');

        $this->from('/checkout')
            ->placeDeposit($user)
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_on_delivery_can_be_switched_off_leaving_only_the_deposit(): void
    {
        $this->assertTrue(Setting::put('cod_enabled', '0'));

        $user = $this->customer();
        $this->basket($user);

        $this->get('/checkout')->assertOk()->assertDontSee('تدفع نقداً للمندوب وقت تسليم الطلب.');

        // The default method is no longer on offer, so plain COD is refused…
        $this->from('/checkout')
            ->post('/checkout', ['address_id' => $user->addresses()->first()->id])
            ->assertSessionHas('error');

        // …while the deposit goes through.
        $this->placeDeposit($user)->assertSessionHas('success');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_a_nonsense_deposit_percentage_falls_back_to_the_default(): void
    {
        Setting::put('deposit_percent', '140');

        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $this->assertSame('20.00', Order::latest('id')->sole()->deposit_percent);
    }

    public function test_the_shop_can_ask_for_a_different_share(): void
    {
        Setting::put('deposit_percent', '35');

        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();

        $this->assertSame('35.00', $order->deposit_percent);
        $this->assertSame('350.00', $order->deposit_amount);

        $order->approveDeposit();

        $this->assertSame(650.0, $order->remainingAmount());
    }

    public function test_the_customer_may_transfer_more_than_the_minimum(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->placeDeposit($user, ['transfer_amount' => 650]);

        $order = Order::latest('id')->sole();

        $this->assertSame('650.00', $order->deposit_amount);
        // The share is what was actually sent, not the configured floor.
        $this->assertSame('65.00', $order->deposit_percent);
        $this->assertFalse($order->depositCoversTotal());

        $order->approveDeposit();

        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);
        $this->assertSame(650.0, $order->paidAmount());
        $this->assertSame(350.0, $order->remainingAmount());
    }

    public function test_transferring_the_whole_order_leaves_nothing_for_the_courier(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->placeDeposit($user, ['transfer_amount' => 1000]);

        $order = Order::latest('id')->sole();

        $this->assertSame('1000.00', $order->deposit_amount);
        $this->assertSame('100.00', $order->deposit_percent);
        $this->assertTrue($order->depositCoversTotal());

        // Still unreviewed: a full transfer is not money until it is accepted.
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(
            'الإجمالي 1,000.00 ج.م · تحويل كامل القيمة بانتظار الاعتماد · لا شيء للتحصيل عند الاستلام',
            $order->deliveryNote(),
        );

        $order->approveDeposit();

        // Accepting it settles the order outright — there is no remainder.
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(1000.0, $order->paidAmount());
        $this->assertSame(0.0, $order->remainingAmount());
        $this->assertSame(
            'الإجمالي 1,000.00 ج.م · مدفوع بالكامل مقدماً · لا شيء للتحصيل عند الاستلام',
            $order->deliveryNote(),
        );

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('لا شيء للتحصيل عند الاستلام');
    }

    public function test_less_than_the_minimum_or_more_than_the_total_is_refused(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->from('/checkout')
            ->placeDeposit($user, ['transfer_amount' => 150])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('transfer_amount');

        $this->from('/checkout')
            ->placeDeposit($user, ['transfer_amount' => 1200])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('transfer_amount');

        // Nothing was placed, and no receipt was left behind on the disk.
        $this->assertSame(0, Order::count());
        $this->assertSame([], Storage::disk('public')->files('deposits'));
    }

    public function test_checkout_asks_for_the_amount_and_prefills_nothing(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $html = $this->get('/checkout')->assertOk()->getContent();

        $this->assertStringContainsString('المبلغ الذي حوّلته', $html);
        $this->assertStringContainsString('أقل مبلغ مطلوب الآن', $html);
        // From 20% of the order, up to all of it, in whole pounds.
        $this->assertStringContainsString('min="200" max="1000"', $html);

        // The customer types what they sent; the field never answers for them.
        $this->assertMatchesRegularExpression('/id="transfer_amount"[^>]*value=""/', $html);
        $this->assertMatchesRegularExpression('/id="transfer_amount"[^>]*required/', $html);
    }

    public function test_the_receipt_field_asks_for_a_file_and_previews_it(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $html = $this->get('/checkout')->assertOk()->getContent();

        // Marked up for the preview, and for the required-attribute toggle —
        // a `required` field inside the hidden panel would block a cash order.
        $this->assertStringContainsString('data-receipt-input', $html);
        $this->assertStringContainsString('data-receipt-preview', $html);
        $this->assertMatchesRegularExpression('/id="deposit_receipt"[^>]*data-deposit-required/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="deposit_receipt"[^>]*\srequired/', $html);

        // Whatever the markup says, the server will not take a deposit order
        // without one.
        $this->from('/checkout')
            ->post('/checkout', [
                'address_id' => $user->addresses()->first()->id,
                'payment_method' => PaymentMethod::Deposit->value,
                'transfer_amount' => 200,
            ])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('deposit_receipt');
    }

    public function test_the_order_page_asks_for_a_replacement_receipt_and_previews_it(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->rejectDeposit('الإيصال غير واضح.');

        $html = $this->get(route('account.orders.show', $order))->assertOk()->getContent();

        // Always on screen here, so the attribute can stay in the markup.
        $this->assertMatchesRegularExpression('/id="deposit_receipt"[^>]*\srequired/', $html);
        $this->assertStringContainsString('data-receipt-preview', $html);
    }

    public function test_a_blank_or_fractional_amount_is_refused(): void
    {
        $user = $this->customer();
        $this->basket($user);

        foreach (['', '200.50', '0', 'كتير'] as $attempt) {
            $this->from('/checkout')
                ->placeDeposit($user, ['transfer_amount' => $attempt])
                ->assertRedirect('/checkout')
                ->assertSessionHasErrors('transfer_amount');
        }

        $this->assertSame(0, Order::count());
        $this->assertSame([], Storage::disk('public')->files('deposits'));
    }

    public function test_a_replacement_receipt_may_name_a_different_amount(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->rejectDeposit('المبلغ المحوَّل أقل من قيمة العربون.');

        // Below the floor: refused, and the rejected state stands.
        $this->from(route('account.orders.show', $order))
            ->post(route('account.orders.deposit-receipt', $order), [
                'deposit_receipt' => UploadedFile::fake()->image('low.png'),
                'transfer_amount' => 50,
            ])
            ->assertSessionHasErrors('transfer_amount');

        $this->assertSame(DepositStatus::Rejected, $order->fresh()->deposit_status);

        $this->post(route('account.orders.deposit-receipt', $order), [
            'deposit_receipt' => UploadedFile::fake()->image('full.png'),
            'transfer_amount' => 1000,
        ])->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(DepositStatus::Submitted, $order->deposit_status);
        $this->assertSame('1000.00', $order->deposit_amount);
        $this->assertSame('100.00', $order->deposit_percent);
        $this->assertTrue($order->depositCoversTotal());
    }

    public function test_a_rejected_receipt_can_be_replaced_and_the_old_file_goes(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $first = $order->deposit_receipt_path;

        $order->rejectDeposit('المبلغ المحوَّل أقل من قيمة العربون.');

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('المبلغ المحوَّل أقل من قيمة العربون.');

        $this->post(route('account.orders.deposit-receipt', $order), [
            'deposit_receipt' => UploadedFile::fake()->image('second.png'),
            'transfer_amount' => 200,
            'transfer_reference' => 'TRX-1111',
        ])->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(DepositStatus::Submitted, $order->deposit_status);
        $this->assertNull($order->deposit_rejection_reason);
        $this->assertNull($order->deposit_reviewed_at);
        $this->assertSame('TRX-1111', $order->deposit_reference);
        $this->assertNotSame($first, $order->deposit_receipt_path);

        Storage::disk('public')->assertExists($order->deposit_receipt_path);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_an_approved_receipt_can_no_longer_be_replaced(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->approveDeposit();

        $this->assertFalse($order->canReplaceDepositReceipt());

        $this->post(route('account.orders.deposit-receipt', $order), [
            'deposit_receipt' => UploadedFile::fake()->image('again.png'),
            'transfer_amount' => 200,
        ])->assertSessionHas('error');

        $this->assertSame('TRX-9087', $order->fresh()->deposit_reference);
    }

    public function test_another_customer_cannot_touch_the_receipt(): void
    {
        $owner = $this->customer();
        $this->basket($owner);
        $this->placeDeposit($owner);

        $order = Order::latest('id')->sole();

        $intruder = User::create([
            'name' => 'سارة',
            'email' => 'sara@example.com',
            'phone' => '01099887766',
            'password' => 'password',
        ]);

        $this->actingAs($intruder)
            ->post(route('account.orders.deposit-receipt', $order), [
                'deposit_receipt' => UploadedFile::fake()->image('theirs.png'),
                'transfer_amount' => 200,
            ])
            ->assertNotFound();
    }

    public function test_a_deposit_order_cannot_be_confirmed_before_the_transfer_is_accepted(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();

        try {
            $order->transitionTo(OrderStatus::Confirmed);
            $this->fail('Confirming an unapproved deposit order should be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('اعتماد', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('order_status_histories', 0);

        // Accepted, it moves on normally.
        $order->approveDeposit();
        $order->transitionTo(OrderStatus::Confirmed);

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_delivering_a_deposit_order_settles_the_remainder(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->approveDeposit();

        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);

        foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $step) {
            $order->transitionTo($step);
        }

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(0.0, $order->remainingAmount());
        $this->assertSame(1000.0, $order->paidAmount());

        // The note still names the deposit that was transferred up front, not
        // everything that has been paid by now.
        $this->assertSame(
            'الإجمالي 1,000.00 ج.م · عربون مدفوع 200.00 ج.م · المتبقي عند الاستلام 0.00 ج.م',
            $order->deliveryNote(),
        );
    }

    public function test_the_review_queue_lists_only_unreviewed_deposits(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $waiting = Order::latest('id')->sole();

        $this->assertTrue($waiting->awaitingDepositReview());
        $this->assertSame(1, Order::query()->depositAwaitingReview()->count());

        $waiting->approveDeposit();

        $this->assertSame(0, Order::query()->depositAwaitingReview()->count());
    }

    public function test_cancelling_an_approved_deposit_order_leaves_the_money_owed_back(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->approveDeposit();

        // Live order: the deposit is the store's to keep until delivery.
        $this->assertFalse($order->awaitingRefund());
        $this->assertSame(0.0, $order->refundableAmount());

        $this->post(route('account.orders.cancel', $order), ['reason' => 'غيّرت رأيي'])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '200.00')
                && str_contains($message, 'سيُرد إليك'));

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        // The transfer is still with the store, and now owed back.
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);
        $this->assertTrue($order->awaitingRefund());
        $this->assertSame(200.0, $order->refundableAmount());
        // Nothing more is coming in on a cancelled order.
        $this->assertSame(0.0, $order->remainingAmount());

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('استرداد المبلغ')
            ->assertSee('مستحق لك')
            ->assertSee('200.00')
            ->assertDontSee('يُدفع للمندوب عند الاستلام');
    }

    public function test_the_customer_sees_the_refund_once_it_has_been_sent(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->approveDeposit();
        $order->transitionTo(OrderStatus::Cancelled, 'نفاد المقاس');

        $order->recordRefund(200.0, 'RFND-1234', 'تحويل إنستاباي على نفس الرقم.');
        $order->refresh();

        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame('200.00', $order->refund_amount);
        $this->assertNotNull($order->refunded_at);
        $this->assertTrue($order->isRefunded());
        // Settled: the store holds nothing of the customer's any more.
        $this->assertFalse($order->awaitingRefund());
        $this->assertSame(0.0, $order->paidAmount());

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('تم رد')
            ->assertSee('RFND-1234')
            ->assertSee('تحويل إنستاباي على نفس الرقم.');

        $this->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('تم رد');
    }

    public function test_a_refund_is_capped_at_what_the_customer_actually_paid(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();
        $order->approveDeposit();

        // Owing nothing yet — the order is still on its way.
        try {
            $order->recordRefund(200.0);
            $this->fail('A live order must not accept a refund.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('مستحق', $e->getMessage());
        }

        $order->transitionTo(OrderStatus::Cancelled, 'غيّرت رأيي');

        try {
            $order->recordRefund(500.0);
            $this->fail('A refund must not exceed what was paid.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('200.00', $e->getMessage());
        }

        $this->assertFalse($order->fresh()->isRefunded());

        // Less than what was taken is allowed — a restocking cut.
        $order->recordRefund(150.0);

        $this->assertSame('150.00', $order->fresh()->refund_amount);
    }

    public function test_a_cancelled_unpaid_order_owes_nothing(): void
    {
        $user = $this->customer();
        $this->basket($user);
        $this->placeDeposit($user);

        $order = Order::latest('id')->sole();

        // The receipt was never accepted, so the store never counted the money.
        $this->post(route('account.orders.cancel', $order), ['reason' => 'تحويل خاطئ']);

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertFalse($order->awaitingRefund());
        $this->assertFalse($order->isRefunded());

        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('مستحق لك');
    }

    public function test_cash_on_delivery_carries_no_deposit_state(): void
    {
        $user = $this->customer();
        $this->basket($user);

        $this->post('/checkout', ['address_id' => $user->addresses()->first()->id]);

        $order = Order::latest('id')->sole();

        $this->assertSame(PaymentMethod::CashOnDelivery, $order->payment_method);
        $this->assertNull($order->deposit_status);
        $this->assertNull($order->deposit_amount);
        $this->assertFalse($order->isDeposit());
        $this->assertSame(1000.0, $order->remainingAmount());
        $this->assertSame('المطلوب تحصيله عند الاستلام 1,000.00 ج.م', $order->deliveryNote());
    }
}
