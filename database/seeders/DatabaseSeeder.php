<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Foundation — required for the app to work at all.
            AdminSeeder::class,
            RolePermissionSeeder::class,
            StaffSeeder::class,
            EgyptLocationSeeder::class,
            SettingSeeder::class,

            // The floukaa.com catalogue. It owns the attributes too — its
            // variation options (مقاس الخاتم، طول السلسلة) are the shop's own,
            // so there is no generic attribute seeder ahead of it.
            FloukaaCatalogSeeder::class,
            CustomerSeeder::class,
            SalesSeeder::class,
        ]);
    }
}
