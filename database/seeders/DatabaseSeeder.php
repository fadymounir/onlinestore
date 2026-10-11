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
            AttributeSeeder::class,
            SettingSeeder::class,

            // Arabic clothes-store demo data.
            ClothingCatalogSeeder::class,
            CustomerSeeder::class,
            SalesSeeder::class,
        ]);
    }
}
