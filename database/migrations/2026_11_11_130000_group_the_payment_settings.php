<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Payment methods get their own tab on the settings page.
 *
 * Switching a method on or off was buried among the currency and minimum-order
 * fields in "الشراء والدفع". The settings page builds its tabs from this
 * column, so regrouping the rows is what moves them — and the deposit's share
 * and transfer details travel with the method they configure.
 */
return new class extends Migration
{
    private const KEYS = [
        'cod_enabled',
        'deposit_enabled',
        'deposit_percent',
        'deposit_instructions',
    ];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->update(['group' => 'payments']);

        Setting::forgetCache();
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->update(['group' => 'checkout']);

        Setting::forgetCache();
    }
};
