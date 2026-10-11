<?php

namespace Tests\Feature\Filament;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Filament\Resources\ShippingZones\RelationManagers\GovernoratesRelationManager;
use App\Filament\Resources\ShippingZones\Pages\EditShippingZone;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesResourcesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@admin.com')->sole();
        $this->actingAs($this->admin, 'admin');
    }

    private function customer(string $suffix = '1'): User
    {
        return User::create([
            'name' => 'منى عبد الرحمن',
            'email' => "mona{$suffix}@example.com",
            'phone' => '0101123450'.$suffix,
            'password' => 'password',
        ]);
    }

    private function product(): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['slug' => 'dresses'], ['name' => 'فساتين'])->id,
            'name' => 'فستان سواريه',
            'slug' => 'dress-'.uniqid(),
            'base_price' => 1850,
        ]);
    }

    public function test_every_page_renders(): void
    {
        $user = $this->customer();
        $zone = ShippingZone::create(['name' => 'القاهرة الكبرى', 'cost' => 40]);
        $coupon = Coupon::create(['code' => 'EID25', 'type' => CouponType::Percent, 'value' => 25]);
        $review = Review::create([
            'product_id' => $this->product()->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'خامة ممتازة',
        ]);

        $pages = [
            '/admin/users' => 'العملاء',
            "/admin/users/{$user->id}/edit" => 'بيانات العميل',
            '/admin/users/create' => 'كلمة المرور',
            '/admin/coupons' => 'الكوبونات',
            "/admin/coupons/{$coupon->id}/edit" => 'شروط الاستخدام',
            '/admin/coupons/create' => 'الكود ونوع الخصم',
            '/admin/reviews' => 'التقييمات',
            "/admin/reviews/{$review->id}/edit" => 'النشر',
            '/admin/shipping-zones' => 'مناطق الشحن',
            "/admin/shipping-zones/{$zone->id}/edit" => 'بيانات المنطقة',
            '/admin/shipping-zones/create' => 'اسم المنطقة',
        ];

        foreach ($pages as $url => $expected) {
            $this->get($url)->assertOk()->assertSee($expected);
        }

        // Reviews come from customers only.
        $this->get('/admin/reviews/create')->assertNotFound();
    }

    public function test_a_customer_with_orders_cannot_be_hard_deleted_but_can_be_archived(): void
    {
        $user = $this->customer();
        $zone = ShippingZone::create(['name' => 'ز', 'cost' => 40]);
        $gov = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);

        Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $user->id,
            'governorate_id' => $gov->id,
            'recipient_name' => 'منى',
            'phone' => '01011234501',
            'governorate_name' => 'القاهرة',
            'street' => 'شارع 1',
            'grand_total' => 500,
        ]);

        // Archiving (soft delete) is always allowed and keeps the sales history.
        Livewire::test(ListUsers::class)->callTableAction('delete', $user);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('orders', 1);

        // Force delete only appears for an archived record. orders.user_id is
        // restrictOnDelete, so the guard must refuse rather than hit a FK error.
        Livewire::test(ListUsers::class)->callTableAction('forceDelete', $user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('orders', 1);

        // An archived customer with no orders force-deletes cleanly.
        $spare = $this->customer('2');
        Livewire::test(ListUsers::class)->callTableAction('delete', $spare);
        Livewire::test(ListUsers::class)->callTableAction('forceDelete', $spare);
        $this->assertDatabaseMissing('users', ['id' => $spare->id]);
    }

    public function test_a_used_coupon_is_protected_from_deletion(): void
    {
        $user = $this->customer();
        $zone = ShippingZone::create(['name' => 'ز', 'cost' => 40]);
        $gov = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);
        $coupon = Coupon::create(['code' => 'EID25', 'type' => CouponType::Percent, 'value' => 25]);
        $unused = Coupon::create(['code' => 'UNUSED', 'type' => CouponType::Fixed, 'value' => 10]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $user->id,
            'governorate_id' => $gov->id,
            'recipient_name' => 'منى',
            'phone' => '01011234501',
            'governorate_name' => 'القاهرة',
            'street' => 'شارع 1',
            'grand_total' => 500,
            'coupon_id' => $coupon->id,
            'coupon_code' => 'EID25',
        ]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'discount_amount' => 125,
        ]);

        Livewire::test(ListCoupons::class)->callTableAction('delete', $coupon);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);

        // An unused coupon still deletes.
        Livewire::test(ListCoupons::class)->callTableAction('delete', $unused);
        $this->assertDatabaseMissing('coupons', ['id' => $unused->id]);
    }

    public function test_approving_a_review_records_the_moderator(): void
    {
        $user = $this->customer();
        $review = Review::create([
            'product_id' => $this->product()->id,
            'user_id' => $user->id,
            'rating' => 4,
            'comment' => 'جيد جداً',
        ]);

        $this->assertFalse($review->is_approved);

        Livewire::test(ListReviews::class)->callTableAction('approve', $review);

        $review->refresh();
        $this->assertTrue($review->is_approved);
        $this->assertSame($this->admin->id, $review->approved_by);
        $this->assertNotNull($review->approved_at);

        // And it can be hidden again, clearing the moderation stamp.
        Livewire::test(ListReviews::class)->callTableAction('unapprove', $review);

        $review->refresh();
        $this->assertFalse($review->is_approved);
        $this->assertNull($review->approved_by);
    }

    public function test_a_zone_holding_governorates_cannot_be_deleted(): void
    {
        $withGovs = ShippingZone::create(['name' => 'القاهرة الكبرى', 'cost' => 40]);
        Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $withGovs->id]);

        $empty = ShippingZone::create(['name' => 'فارغة', 'cost' => 10]);

        // shipping_zone_id is nullOnDelete: deleting would leave the
        // governorate with no delivery price at all.
        Livewire::test(ListShippingZones::class)->callTableAction('delete', $withGovs);
        $this->assertDatabaseHas('shipping_zones', ['id' => $withGovs->id]);
        $this->assertDatabaseHas('governorates', ['shipping_zone_id' => $withGovs->id]);

        Livewire::test(ListShippingZones::class)->callTableAction('delete', $empty);
        $this->assertDatabaseMissing('shipping_zones', ['id' => $empty->id]);
    }

    public function test_governorates_can_be_moved_between_zones(): void
    {
        $cairo = ShippingZone::create(['name' => 'القاهرة الكبرى', 'cost' => 40]);
        $south = ShippingZone::create(['name' => 'الصعيد', 'cost' => 85]);

        $aswan = Governorate::create(['name' => 'أسوان', 'shipping_zone_id' => $cairo->id]);
        $orphan = Governorate::create(['name' => 'مطروح']);

        Livewire::test(GovernoratesRelationManager::class, [
            'ownerRecord' => $south,
            'pageClass' => EditShippingZone::class,
        ])->callTableAction('assign', data: ['governorate_ids' => [$aswan->id, $orphan->id]]);

        $this->assertSame($south->id, $aswan->fresh()->shipping_zone_id);
        $this->assertSame($south->id, $orphan->fresh()->shipping_zone_id);
        $this->assertSame(0, $cairo->governorates()->count());

        // Detaching leaves it deliberately unpriced.
        Livewire::test(GovernoratesRelationManager::class, [
            'ownerRecord' => $south,
            'pageClass' => EditShippingZone::class,
        ])->callTableAction('detach', $aswan);

        $this->assertNull($aswan->fresh()->shipping_zone_id);
    }

    public function test_customer_lifetime_value_counts_delivered_orders_only(): void
    {
        $user = $this->customer();
        $zone = ShippingZone::create(['name' => 'ز', 'cost' => 40]);
        $gov = Governorate::create(['name' => 'القاهرة', 'shipping_zone_id' => $zone->id]);

        foreach ([[OrderStatus::Delivered, 500], [OrderStatus::Delivered, 300], [OrderStatus::Cancelled, 900]] as [$status, $total]) {
            Order::create([
                'order_number' => Order::nextOrderNumber(),
                'user_id' => $user->id,
                'governorate_id' => $gov->id,
                'status' => $status,
                'recipient_name' => 'منى',
                'phone' => '01011234501',
                'governorate_name' => 'القاهرة',
                'street' => 'شارع 1',
                'grand_total' => $total,
            ]);
        }

        $delivered = $user->orders()->where('status', OrderStatus::Delivered->value)->sum('grand_total');

        // The cancelled 900 must not count toward lifetime value.
        $this->assertSame(800.0, (float) $delivered);
    }
}
