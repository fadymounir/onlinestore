<?php

namespace Tests\Feature\Shop;

use App\Models\Governorate;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront's pager, registered as Laravel's default view in
 * AppServiceProvider.
 *
 * Worth testing rather than eyeballing: the registration is one line in a
 * provider, and if it is ever dropped the stock Tailwind view takes over
 * silently — English labels and a blue palette on an Arabic amber storefront,
 * with nothing failing to say so.
 *
 * The order history is what exercises it now. Product listings have no pager:
 * they load as the shopper scrolls, which EndlessListingTest covers.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona@example.com',
            'phone' => '01011234501',
            'password' => 'password',
        ]);

        $zone = ShippingZone::create(['name' => 'الدلتا', 'cost' => 40]);
        $governorate = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);

        // The history pages at 10, so 13 is two pages with a short second.
        foreach (range(1, 13) as $i) {
            Order::create([
                'order_number' => Order::nextOrderNumber(),
                'user_id' => $this->customer->id,
                'governorate_id' => $governorate->id,
                'recipient_name' => 'منى عبد الرحمن',
                'phone' => '01011234501',
                'governorate_name' => 'القاهرة',
                'street' => "شارع رقم {$i}",
                'grand_total' => 100 + $i,
            ]);
        }
    }

    public function test_the_order_history_renders_the_shops_own_pager(): void
    {
        $this->actingAs($this->customer)
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('التنقل بين الصفحات')
            ->assertSee('السابق')
            ->assertSee('التالي')
            // The count line, in Arabic and in Western digits to match <x-price>.
            ->assertSee('عرض')
            ->assertSee('من')
            // The current page is marked, not merely coloured.
            ->assertSee('aria-current="page"', escape: false)
            ->assertSee('bg-brand-500', escape: false);
    }

    public function test_the_stock_tailwind_pager_is_no_longer_used(): void
    {
        // These are the stock view and nothing else: its English count line,
        // and the physical rounding that sits on the wrong side of an RTL
        // document.
        $this->actingAs($this->customer)
            ->get(route('account.orders.index'))
            ->assertDontSee('Showing')
            ->assertDontSee('rounded-l-md', escape: false)
            ->assertDontSee('rounded-r-md', escape: false);
    }

    public function test_the_second_page_is_reachable_and_marks_where_it_is(): void
    {
        $this->actingAs($this->customer)
            ->get(route('account.orders.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('aria-current="page"', escape: false)
            // Page 2 of 13 at 10 a page: items 11-13.
            ->assertSee('11')
            ->assertSee('13')
            ->assertSee('الصفحة السابقة');
    }

    public function test_a_single_page_of_results_renders_no_pager(): void
    {
        Order::query()->where('grand_total', '>', 104)->delete();

        $this->actingAs($this->customer)
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertDontSee('التنقل بين الصفحات');
    }
}
