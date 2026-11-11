<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Whether a customer may file a new delivery address of their own.
 *
 * On by default: checkout needs an address, so a shop that starts with this
 * off cannot take an order from anyone who has not already got one on file.
 * Switching it off is the deliberate act, not the starting point.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'customer_addresses_enabled',
            'label' => 'السماح للعميل بإضافة عناوين جديدة',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'checkout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::forgetCache();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'customer_addresses_enabled')->delete();

        Setting::forgetCache();
    }
};
