<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('المخزون')
            ->assertSee('التقييمات')
            ->assertSee('اسم المتجر');
    }

    public function test_it_loads_the_current_values(): void
    {
        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet([
                'store_name' => 'متجري',
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
        $this->assertSame('متجري', Setting::get('store_name'));

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

        // Staff keep working (the admin guard is still authenticated here).
        $this->get('/admin')->assertOk();
        $this->get('/')->assertOk();

        // A plain visitor gets the closed-shop page.
        auth('admin')->logout();
        $this->get('/')->assertStatus(503)->assertSee('المتجر مغلق مؤقتاً للصيانة');
        $this->get('/products')->assertStatus(503);
    }

    public function test_the_announcement_bar_is_editable_and_hides_when_empty(): void
    {
        Livewire::test(ManageSettings::class)
            ->fillForm(['announcement_text' => 'خصم 30% على كل الفساتين'])
            ->call('save');

        $this->get('/')->assertOk()->assertSee('خصم 30% على كل الفساتين');

        Livewire::test(ManageSettings::class)
            ->fillForm(['announcement_text' => ''])
            ->call('save');

        $this->get('/')->assertOk()->assertDontSee('خصم 30% على كل الفساتين');
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
