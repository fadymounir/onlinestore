<?php

namespace Tests\Feature\Filament;

use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
    }

    private function admin(): Admin
    {
        return Admin::where('email', 'admin@admin.com')->sole();
    }

    public function test_the_whole_panel_renders_for_a_signed_in_admin(): void
    {
        $this->actingAs($this->admin(), 'admin');

        foreach ([
            '/admin',
            '/admin/admins',
            '/admin/admins/create',
            '/admin/roles',
            '/admin/roles/create',
            '/admin/profile',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_panel_is_always_arabic_and_rtl(): void
    {
        $this->actingAs($this->admin(), 'admin');

        // Even when the browser explicitly asks for English.
        $this->get('/admin', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertSee('lang="ar"', escape: false)
            ->assertSee('dir="rtl"', escape: false);

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_the_custom_arabic_forms_and_profile_are_the_ones_rendered(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/admins/create')
            ->assertOk()
            ->assertSee('بيانات المشرف')
            ->assertSee('كلمة المرور')
            ->assertSee('الأدوار');

        $this->get('/admin/roles/create')
            ->assertOk()
            ->assertSee('بيانات الدور')
            ->assertSee('الصلاحيات');

        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee('البيانات الشخصية')
            ->assertSee('تغيير كلمة المرور')
            ->assertSee('بيانات الحساب');

        $this->get('/admin/admins')
            ->assertOk()
            ->assertSee('إدارة النظام')   // navigation group
            ->assertSee('المشرفون');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/admins')->assertRedirect('/admin/login');
    }

    public function test_password_reset_pages_are_reachable(): void
    {
        $this->get('/admin/password-reset/request')->assertOk();
    }

    public function test_an_inactive_admin_cannot_reach_the_panel(): void
    {
        $admin = $this->admin();
        $admin->update(['is_active' => false]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_the_seeded_admin_is_a_super_admin_on_the_admin_guard(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertSame('admin', $admin->roles->first()->guard_name);
        $this->assertTrue($admin->can('any-ability-at-all'));
    }
}
