<?php

namespace Tests\Feature\Filament;

use App\Enums\PaymentMethod;
use App\Filament\Pages\ManageSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    public function test_the_page_renders_every_group_in_arabic(): void
    {
        $this->get('/admin/manage-settings')
            ->assertOk()
            ->assertSee('إعدادات المتجر')
            ->assertSee('عام')
            ->assertSee('بيانات التواصل')
            ->assertSee('السوشيال ميديا')
            ->assertSee('الشراء والدفع')
            ->assertSee('طرق الدفع')
            ->assertSee('المخزون')
            ->assertSee('التقييمات')
            ->assertSee('اسم المتجر')
            // The orders switch sits under عام beside وضع الصيانة: the two
            // ways to close the shop belong side by side.
            ->assertSee('إيقاف الطلبات مؤقتاً (التصفح والسلة يظلان يعملان)');
    }

    public function test_it_loads_the_current_values(): void
    {
        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet([
                'store_name' => 'Simple',
                'currency_symbol' => 'ج.م',
            ], 'form');
    }

    public function test_saving_writes_every_type_back_correctly(): void
    {
        Livewire::test(ManageSettings::class)
            ->fillForm([
                'store_name' => 'متجر الأناقة',
                'store_phone' => '01099887766',
                'store_address' => 'المعادي، القاهرة',
                'min_order_total' => '250',
                'cod_enabled' => false,
                'maintenance_mode' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('متجر الأناقة', Setting::get('store_name'));
        $this->assertSame('01099887766', Setting::get('store_phone'));
        $this->assertSame('المعادي، القاهرة', Setting::get('store_address'));
        $this->assertSame('250', Setting::get('min_order_total'));

        // Booleans must land as "1"/"0" so the storefront's @if checks hold.
        $this->assertSame('0', Setting::get('cod_enabled'));
        $this->assertSame('1', Setting::get('maintenance_mode'));
    }

    public function test_saving_busts_the_settings_cache(): void
    {
        // Warm the cache through the accessor the storefront uses.
        $this->assertSame('Simple', Setting::get('store_name'));

        Livewire::test(ManageSettings::class)
            ->fillForm(['store_name' => 'اسم جديد'])
            ->call('save');

        // Setting::booted() forgets the cache on save, so no stale read.
        $this->assertSame('اسم جديد', Setting::get('store_name'));
    }

    public function test_the_storefront_picks_up_a_renamed_store(): void
    {
        Livewire::test(ManageSettings::class)
            ->fillForm(['store_name' => 'بوتيك لمسة'])
            ->call('save');

        $this->get('/')->assertOk()->assertSee('بوتيك لمسة');
    }

    public function test_clearing_a_field_stores_null_rather_than_an_empty_string(): void
    {
        Livewire::test(ManageSettings::class)
            ->fillForm(['store_whatsapp' => ''])
            ->call('save');

        $this->assertNull(Setting::get('store_whatsapp'));
    }

    public function test_a_new_setting_row_appears_without_touching_the_page(): void
    {
        Setting::create([
            'key' => 'promo_banner',
            'label' => 'بانر العروض',
            'value' => 'خصم 20% هذا الأسبوع',
            'type' => 'string',
            'group' => 'general',
        ]);

        $this->get('/admin/manage-settings')
            ->assertOk()
            ->assertSee('بانر العروض');

        Livewire::test(ManageSettings::class)
            ->fillForm(['promo_banner' => 'خصم 30%'])
            ->call('save');

        $this->assertSame('خصم 30%', Setting::get('promo_banner'));
    }

    public function test_maintenance_mode_closes_the_shop_but_not_the_panel(): void
    {
        Livewire::test(ManageSettings::class)
            ->fillForm(['maintenance_mode' => true])
            ->call('save');

        // The panel keeps working — that is the whole point of the switch
        // being reachable while the shop is shut.
        $this->get('/admin')->assertOk();

        // The shop closes for everyone, the signed-in admin included, so a
        // staff member testing the switch sees what a customer sees.
        $this->get('/')->assertStatus(503)->assertSee('المتجر مغلق مؤقتاً للصيانة');

        auth('admin')->logout();
        $this->get('/')->assertStatus(503)->assertSee('المتجر مغلق مؤقتاً للصيانة');
        $this->get('/products')->assertStatus(503);

        // Signing back in has to keep working. Filament drives every panel
        // interaction — the login form included — through Livewire's update
        // endpoint, and that one *does* ride the `web` group: closing it
        // would 503 the login submit and lock staff out of the very switch
        // that lifts maintenance mode.
        $this->get('/admin/login')->assertOk();

        $this->assertNotSame(
            503,
            $this->withHeader('X-Livewire', '1')
                ->post(Livewire::getUpdateUri())
                ->getStatusCode(),
        );
    }

    public function test_an_uploaded_logo_replaces_the_built_in_badge(): void
    {
        // Empty is not broken: the generated badge stands in until a shop
        // uploads its own, so the storefront always has a mark.
        $this->assertNull(Setting::get('store_logo'));
        $this->assertSame(asset('images/logo-192.png'), Setting::logoUrl());

        Storage::fake('public');

        Livewire::test(ManageSettings::class)
            ->fillForm(['store_logo' => UploadedFile::fake()->image('brand.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = Setting::get('store_logo');
        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);

        // The uploaded file is what the header, the footer, the tab icon and
        // the panel brand all render — the setting was dead wiring before.
        $url = Storage::disk('public')->url($stored);
        $this->assertSame($url, Setting::logoUrl());
        $this->assertSame($url, Setting::faviconUrl());

        auth('admin')->logout();
        $this->get('/')
            ->assertOk()
            ->assertSee($url)
            ->assertSee('<link rel="icon" href="'.$url.'">', false)
            // The built-in set stands down rather than competing with it.
            ->assertDontSee(asset('favicon.ico'));

        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
        $this->get('/admin')->assertOk()->assertSee($url);
    }

    public function test_the_built_in_icons_are_used_until_a_logo_is_uploaded(): void
    {
        $this->assertSame(asset('favicon.ico'), Setting::faviconUrl());

        auth('admin')->logout();
        $this->get('/')
            ->assertOk()
            ->assertSee(asset('favicon.ico'))
            ->assertSee(asset('images/logo-192.png'))
            ->assertSee(asset('images/apple-touch-icon.png'));
    }

    public function test_the_payment_methods_have_a_tab_of_their_own(): void
    {
        // Switching a method off is the point of the tab, so the toggles and
        // the deposit's own settings have to be the things on it.
        $this->get('/admin/manage-settings')
            ->assertOk()
            ->assertSee('طرق الدفع')
            ->assertSee('تفعيل الدفع عند الاستلام')
            ->assertSee('تفعيل الدفع بعربون (تحويل بنكي)')
            ->assertSee('الحد الأدنى للعربون % من إجمالي الطلب');

        $this->assertSame(
            ['cod_enabled', 'deposit_enabled', 'deposit_percent', 'deposit_instructions'],
            $this->keysIn('payments'),
        );

        // Currency, the order minimum and the address switch stay where
        // they were — they are about the order, not about how it is paid.
        $this->assertSame(
            ['currency_code', 'currency_symbol', 'min_order_total', 'customer_addresses_enabled'],
            $this->keysIn('checkout'),
        );
    }

    /**
     * A group's keys in the order the page lays them out. Migrations insert
     * settings rows of their own and claim the low ids, so `id` is not it.
     *
     * @return array<int, string>
     */
    private function keysIn(string $group): array
    {
        $declared = array_flip(SettingSeeder::declaredOrder());

        return Setting::where('group', $group)
            ->pluck('key')
            ->sortBy(fn (string $key): int => $declared[$key] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    public function test_the_tabs_follow_the_seeders_order_not_the_row_ids(): void
    {
        // The deposit rows are created by a migration, so on a fresh install
        // they hold ids 1-3 — ahead of the store name. The page must not
        // read that as "show the deposit fields first".
        $this->assertLessThan(
            Setting::where('key', 'store_name')->value('id'),
            Setting::where('key', 'deposit_enabled')->value('id'),
        );

        $this->assertSame(
            ['store_name', 'store_tagline', 'store_logo', 'maintenance_mode', 'ordering_paused'],
            $this->keysIn('general'),
        );
    }

    public function test_turning_a_method_off_takes_it_off_the_checkout_page(): void
    {
        Livewire::test(ManageSettings::class)
            ->fillForm(['deposit_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0', Setting::get('deposit_enabled'));
        $this->assertFalse(PaymentMethod::Deposit->isEnabled());
        $this->assertSame([PaymentMethod::CashOnDelivery], PaymentMethod::enabled());
    }

    public function test_restoring_saved_values_discards_an_unsaved_draft(): void
    {
        $page = Livewire::test(ManageSettings::class)
            ->fillForm(['store_name' => 'مسودة لم تُحفظ'], 'form');

        $this->assertSame('مسودة لم تُحفظ', $page->get('data')['store_name']);

        // Called the way the rendered button calls it — the action lives in
        // the content schema's footer, not among the page's own actions.
        $page->call('mountAction', 'reload', [], ['schemaComponent' => 'content']);

        // The draft is gone from the component's state, and nothing reached
        // the database on the way.
        $this->assertSame('Simple', $page->get('data')['store_name']);
        $this->assertSame('Simple', Setting::get('store_name'));
    }

    public function test_a_setting_in_an_unknown_group_still_gets_a_tab(): void
    {
        Setting::create([
            'key' => 'seo_title',
            'label' => 'عنوان الصفحة لمحركات البحث',
            'type' => 'string',
            'group' => 'seo',
        ]);

        // No Arabic label is declared for "seo", so the raw group is the tab
        // title — the setting must never silently vanish from the page.
        $this->get('/admin/manage-settings')
            ->assertOk()
            ->assertSee('عنوان الصفحة لمحركات البحث');
    }
}
