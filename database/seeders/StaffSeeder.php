<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /** [name, email, role, active] — runs after RolePermissionSeeder. */
    private const STAFF = [
        ['سلمى عبد الله', 'manager@admin.com', 'مدير المتجر', true],
        ['إسلام رفعت', 'stock@admin.com', 'مسؤول المخزون', true],
        ['ريم جمال', 'support@admin.com', 'خدمة العملاء', true],
        ['هاني عبد الفتاح', 'accounts@admin.com', 'محاسب', true],
        ['مصطفى لطفي', 'former@admin.com', 'خدمة العملاء', false],
    ];

    public function run(): void
    {
        foreach (self::STAFF as [$name, $email, $roleName, $active]) {
            $admin = Admin::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'is_active' => $active,
                    'email_verified_at' => now()->subMonth(),
                ],
            );

            $role = Role::where('name', $roleName)->where('guard_name', 'admin')->first();

            if ($role) {
                $admin->syncRoles([$role]);
            }
        }
    }
}
