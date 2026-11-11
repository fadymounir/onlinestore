<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A softer switch than `maintenance_mode`: the shop stays open to browse and
 * to fill a cart, but no order can be placed. It sits in the same group so
 * the two switches read together on the settings page, which builds its
 * fields from this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'ordering_paused',
            'label' => 'إيقاف الطلبات مؤقتاً (التصفح والسلة يظلان يعملان)',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::forgetCache();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'ordering_paused')->delete();

        Setting::forgetCache();
    }
};
