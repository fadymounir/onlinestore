<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The role form groups the flat `resource.ability` permissions by resource,
 * under Arabic headings, and puts them back together on save.
 */
class RolePermissionsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    public function test_the_create_page_groups_the_permissions_under_arabic_headings(): void
    {
        $page = $this->get('/admin/roles/create')->assertOk();

        foreach (AdminPermissions::RESOURCES as $heading) {
            $page->assertSee($heading);
        }

        foreach (AdminPermissions::ABILITIES as $label) {
            $page->assertSee($label);
        }

        // The raw identifiers still bind the switches — `abilities.products
        // .create` is a wire:model path — but they are never reading matter.
        $this->assertStringNotContainsString(
            'products.create',
            strip_tags($page->getContent()),
        );

        $page->assertSee('تحديد كل الصلاحيات')->assertSee('إلغاء تحديد الكل');
    }

    public function test_the_select_all_action_switches_every_permission_on(): void
    {
        $page = Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'مدير عام'])
            ->call('mountAction', 'grantAll', [], ['schemaComponent' => 'form']);

        $this->assertSame(AdminPermissions::matrix(granted: true), $page->get('data')['abilities']);

        $page->call('create')->assertHasNoFormErrors();

        $this->assertSame(
            collect(AdminPermissions::names())->sort()->values()->all(),
            Role::where('name', 'مدير عام')->sole()->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_the_clear_action_switches_every_permission_off(): void
    {
        $role = Role::where('name', 'مدير المتجر')->sole();
        $this->assertNotEmpty($role->permissions);

        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->call('mountAction', 'revokeAll', [], ['schemaComponent' => 'form'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEmpty($role->fresh()->permissions);
    }

    public function test_creating_a_role_turns_the_ticks_into_permission_names(): void
    {
        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'مسؤول الشحن',
                'abilities' => [
                    'shipping' => ['view' => true, 'update' => true],
                    'orders' => ['view' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'مسؤول الشحن')->sole();

        $this->assertSame(
            ['orders.view', 'shipping.update', 'shipping.view'],
            $role->permissions->pluck('name')->sort()->values()->all(),
        );
        $this->assertSame('admin', $role->guard_name);
    }

    public function test_editing_a_role_shows_its_permissions_grouped_and_saves_them_back(): void
    {
        $role = Role::where('name', 'محاسب')->sole();

        $page = Livewire::test(EditRole::class, ['record' => $role->getKey()]);

        // Seeded as orders.view, coupons.view, products.view. Every other
        // resource is present but empty — each box renders either way.
        $abilities = collect($page->get('data')['abilities']);

        $this->assertSame(
            ['coupons' => ['view'], 'orders' => ['view'], 'products' => ['view']],
            $abilities
                ->map(fn (array $row): array => array_keys(array_filter($row)))
                ->filter()
                ->sortKeys()
                ->all(),
        );
        // Every resource gets a box, ticked or not. The order they appear
        // in is the form's, not this array's.
        $this->assertSame(
            collect(AdminPermissions::RESOURCES)->keys()->sort()->values()->all(),
            $abilities->keys()->sort()->values()->all(),
        );

        $page->fillForm([
            'abilities' => [
                'orders' => ['view' => true, 'update' => true],
                'coupons' => ['view' => true],
                'products' => ['view' => false],
            ],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame(
            ['coupons.view', 'orders.update', 'orders.view'],
            $role->fresh()->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_the_vocabulary_matches_the_permissions_in_the_database(): void
    {
        // A permission with no heading would be invisible on the form, and a
        // heading with no permission would be an empty box.
        $this->assertSame(
            collect(AdminPermissions::names())->sort()->values()->all(),
            Permission::where('guard_name', 'admin')->pluck('name')->sort()->values()->all(),
        );
    }
}
