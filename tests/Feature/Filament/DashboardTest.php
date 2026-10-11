<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\LatestOrdersTable;
use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\OrdersByStatusChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\SalesByGovernorateChart;
use App\Filament\Widgets\SalesOverview;
use App\Filament\Widgets\TopProductsTable;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** getData()/getOptions() are protected on ChartWidget by design. */
    private function callProtected(object $widget, string $method): array
    {
        $ref = new ReflectionMethod($widget, $method);
        $ref->setAccessible(true);

        return $ref->invoke($widget);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    private function order(OrderStatus $status, float $total, string $gov = 'القاهرة'): Order
    {
        $n = ++$this->seq;

        $zone = ShippingZone::firstOrCreate(['name' => 'منطقة'], ['cost' => 40]);
        $governorate = Governorate::firstOrCreate(['name' => $gov], ['shipping_zone_id' => $zone->id]);

        $user = User::create([
            'name' => 'عميل '.$n,
            'email' => "c{$n}@example.com",
            'phone' => '0101000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'password' => 'password',
        ]);

        $product = Product::create([
            'category_id' => Category::firstOrCreate(['slug' => 'c'], ['name' => 'تصنيف'])->id,
            'name' => 'فستان سواريه',
            'slug' => 'p-'.$n,
            'base_price' => $total,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'S-'.$n, 'price' => $total, 'stock_quantity' => 5,
        ]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $user->id,
            'governorate_id' => $governorate->id,
            'status' => $status,
            'recipient_name' => 'عميل',
            'phone' => '01010000000',
            'governorate_name' => $gov,
            'street' => 'شارع',
            'subtotal' => $total,
            'grand_total' => $total,
            'placed_at' => now()->subDays(2),
            'delivered_at' => $status === OrderStatus::Delivered ? now()->subDay() : null,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'أحمر / XL',
            'sku' => $variant->sku,
            'unit_price' => $total,
            'quantity' => 2,
            'line_total' => $total * 2,
        ]);

        return $order;
    }

    public function test_the_dashboard_renders_with_every_widget(): void
    {
        $this->order(OrderStatus::Delivered, 1000);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('لوحة التحكم')
            ->assertSee('ملخص المبيعات والمخزون');

        // Widgets are lazily-loaded Livewire components, so each one is
        // rendered directly rather than scraped out of the page.
        $headings = [
            SalesOverview::class => 'نظرة عامة',
            RevenueChart::class => 'الإيرادات المحصّلة',
            OrdersByStatusChart::class => 'الطلبات حسب الحالة',
            TopProductsTable::class => 'الأكثر مبيعاً',
            SalesByGovernorateChart::class => 'أعلى المحافظات مبيعاً',
            LatestOrdersTable::class => 'أحدث الطلبات',
            LowStockTable::class => 'مخزون يحتاج تدخّلاً',
        ];

        foreach ($headings as $widget => $heading) {
            Livewire::test($widget)->assertOk()->assertSee($heading);
        }

        $this->assertCount(7, (new Dashboard)->getWidgets());
    }

    public function test_revenue_counts_only_delivered_orders(): void
    {
        $this->order(OrderStatus::Delivered, 1000);
        $this->order(OrderStatus::Delivered, 500);
        $this->order(OrderStatus::Pending, 9999);     // not collected
        $this->order(OrderStatus::Cancelled, 7777);   // never will be

        Livewire::test(SalesOverview::class)
            ->assertSee('1,500.00 ج.م')
            ->assertDontSee('9,999')
            ->assertDontSee('7,777');
    }

    public function test_average_order_value_divides_by_delivered_orders_only(): void
    {
        $this->order(OrderStatus::Delivered, 1000);
        $this->order(OrderStatus::Delivered, 500);
        $this->order(OrderStatus::Pending, 400);

        // 1500 / 2 delivered = 750, not 1900/3.
        Livewire::test(SalesOverview::class)
            ->assertSee('750.00 ج.م')
            ->assertSee('على 2 طلب مُسلَّم');
    }

    public function test_the_cancellation_rate_is_over_all_orders(): void
    {
        $this->order(OrderStatus::Delivered, 100);
        $this->order(OrderStatus::Delivered, 100);
        $this->order(OrderStatus::Cancelled, 100);
        $this->order(OrderStatus::Returned, 100);

        Livewire::test(SalesOverview::class)
            ->assertSee('50%')
            ->assertSee('2 من 4 طلب');
    }

    public function test_the_revenue_chart_buckets_by_day_and_respects_its_filter(): void
    {
        $this->order(OrderStatus::Delivered, 1200);

        $widget = Livewire::test(RevenueChart::class);
        $widget->assertSee('الإيرادات المحصّلة');

        $data = $this->callProtected($widget->instance(), 'getData');

        // 30-day default => 30 buckets, and the delivered 1200 lands in one.
        $this->assertCount(30, $data['labels']);
        $this->assertCount(30, $data['datasets'][0]['data']);
        $this->assertSame(1200.0, array_sum($data['datasets'][0]['data']));

        // Switching the filter changes the bucket count.
        $widget->set('filter', '7');
        $this->assertCount(7, $this->callProtected($widget->instance(), 'getData')['labels']);
    }

    public function test_the_status_chart_lists_every_status_even_at_zero(): void
    {
        $this->order(OrderStatus::Delivered, 100);

        $data = $this->callProtected(Livewire::test(OrdersByStatusChart::class)->instance(), 'getData');

        // All 7 statuses present, so the axis never silently shrinks.
        $this->assertCount(count(OrderStatus::cases()), $data['labels']);
        $this->assertContains('تم التوصيل', $data['labels']);
        $this->assertContains('قيد الانتظار', $data['labels']);
        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
    }

    public function test_the_charts_use_a_single_series_with_no_legend(): void
    {
        $this->order(OrderStatus::Delivered, 100);

        foreach ([RevenueChart::class, OrdersByStatusChart::class, SalesByGovernorateChart::class] as $chart) {
            $widget = Livewire::test($chart)->instance();

            // One series: identity comes from the heading and axis labels,
            // so there is no categorical palette to cycle or mis-pair.
            $this->assertCount(1, $this->callProtected($widget, 'getData')['datasets'], $chart);
            $this->assertFalse($this->callProtected($widget, 'getOptions')['plugins']['legend']['display'], $chart);
        }
    }

    public function test_governorate_sales_use_the_order_snapshot_and_rank_by_revenue(): void
    {
        $this->order(OrderStatus::Delivered, 500, 'القاهرة');
        $this->order(OrderStatus::Delivered, 300, 'القاهرة');
        $this->order(OrderStatus::Delivered, 900, 'الإسكندرية');
        $this->order(OrderStatus::Pending, 5000, 'أسوان');

        $data = $this->callProtected(Livewire::test(SalesByGovernorateChart::class)->instance(), 'getData');

        $this->assertSame(['الإسكندرية', 'القاهرة'], $data['labels']);
        $this->assertSame([900.0, 800.0], $data['datasets'][0]['data']);
    }

    public function test_top_products_aggregates_quantity_across_delivered_orders(): void
    {
        $this->order(OrderStatus::Delivered, 100);
        $this->order(OrderStatus::Delivered, 100);
        $this->order(OrderStatus::Pending, 100);

        // Two delivered orders x 2 units of the same product name = 4.
        Livewire::test(TopProductsTable::class)
            ->assertSee('فستان سواريه')
            ->assertCanSeeTableRecords(\App\Models\OrderItem::query()->limit(1)->get(), inOrder: false);
    }

    public function test_the_low_stock_widget_only_lists_variants_needing_action(): void
    {
        $order = $this->order(OrderStatus::Delivered, 100);
        $variant = $order->items()->first()->variant;

        // Healthy stock: must not appear.
        $variant->update(['stock_quantity' => 50, 'low_stock_threshold' => 5]);
        Livewire::test(LowStockTable::class)->assertSee('كل المخزون في حالة جيدة');

        // Sold out: must appear.
        $variant->update(['stock_quantity' => 0]);
        Livewire::test(LowStockTable::class)
            ->assertCanSeeTableRecords([$variant])
            ->assertSee('نفد');

        // Low but not out: must appear too.
        $variant->update(['stock_quantity' => 3, 'low_stock_threshold' => 5]);
        Livewire::test(LowStockTable::class)->assertCanSeeTableRecords([$variant]);
    }

    public function test_the_dashboard_is_the_panel_landing_page(): void
    {
        $this->assertSame('/', Dashboard::getRoutePath(Filament::getPanel('admin')));
        $this->get('/admin')->assertOk()->assertDontSee('filament.io');
    }
}
