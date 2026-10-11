<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Resource-level permissions get added here once the store schema lands.
        $superAdmin = Role::findOrCreate('super_admin', 'admin');

        $admin = Admin::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'مدير النظام',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([$superAdmin]);
    }
}
