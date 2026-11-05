<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The storefront no longer has an announcement bar, and the settings page
 * builds its fields from this table — so the row has to go, or the dashboard
 * keeps offering a field that renders nowhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'announcement_text')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'announcement_text',
            'label' => 'شريط الإعلان أعلى الموقع',
            'value' => null,
            'type' => 'string',
            'group' => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
