<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * The resources and abilities themselves live in AdminPermissions, which
     * the role form reads too — a permission the seeder invented on its own
     * would have no heading to appear under there.
     */
    private const ABILITIES = ['view', 'create', 'update', 'delete'];

    /** role => [resource => abilities] | '*' for everything on that resource */
    private const ROLES = [
        'super_admin' => [],   // bypasses every check via Gate::before
        'مدير المتجر' => [
            'products' => '*', 'categories' => '*', 'brands' => '*', 'variants' => '*',
            'orders' => '*', 'customers' => ['view', 'update'], 'coupons' => '*',
            'reviews' => '*', 'shipping' => '*', 'settings' => ['view', 'update'],
            'pages' => '*',
        ],
        'مسؤول المخزون' => [
            'products' => ['view', 'update'], 'variants' => ['view', 'create', 'update'],
            'categories' => ['view'], 'brands' => ['view'],
        ],
        'خدمة العملاء' => [
            'orders' => ['view', 'update'], 'customers' => ['view'],
            'reviews' => ['view', 'update'], 'products' => ['view'], 'variants' => ['view'],
        ],
        'محاسب' => [
            'orders' => ['view'], 'coupons' => ['view'], 'products' => ['view'],
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermissions::names() as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        foreach (self::ROLES as $roleName => $grants) {
            $role = Role::findOrCreate($roleName, 'admin');

            $names = [];

            foreach ($grants as $resource => $abilities) {
                $abilities = $abilities === '*' ? self::ABILITIES : $abilities;

                foreach ($abilities as $ability) {
                    $names[] = "{$resource}.{$ability}";
                }
            }

            $role->syncPermissions($names);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
