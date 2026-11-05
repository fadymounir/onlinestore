<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mirrors Database\Seeders\SettingSeeder's deposit rows. */
    private const SETTINGS = [
        [
            'key' => 'deposit_enabled',
            'label' => 'تفعيل الدفع بعربون (تحويل بنكي)',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'checkout',
        ],
        [
            'key' => 'deposit_percent',
            'label' => 'الحد الأدنى للعربون % من إجمالي الطلب',
            'value' => '20',
            'type' => 'number',
            'group' => 'checkout',
        ],
        [
            'key' => 'deposit_instructions',
            'label' => 'بيانات التحويل الظاهرة للعميل',
            'value' => "حوّل قيمة العربون على أحد الحسابات التالية ثم ارفع صورة الإيصال:\n"
                ."• إنستاباي / محفظة: 01000000000\n"
                .'• حساب بنكي: Simple Store — 1000 0000 0000 0000',
            'type' => 'text',
            'group' => 'checkout',
        ],
    ];

    /**
     * Partial prepayment: the customer transfers a percentage of the order up
     * front and pays the rest on delivery. Every figure is SNAPSHOTTED here —
     * changing the `deposit_percent` setting later must not rewrite what an
     * old order asked for.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('deposit_status', 20)->nullable()->after('payment_status');  // App\Enums\DepositStatus
            $table->decimal('deposit_percent', 5, 2)->nullable()->after('deposit_status');
            $table->decimal('deposit_amount', 12, 2)->nullable()->after('deposit_percent');

            // The transfer proof the customer attached, plus whatever reference
            // the bank or wallet gave them.
            $table->string('deposit_receipt_path')->nullable()->after('deposit_amount');
            $table->string('deposit_reference')->nullable()->after('deposit_receipt_path');
            $table->string('deposit_rejection_reason')->nullable()->after('deposit_reference');

            $table->timestamp('deposit_submitted_at')->nullable()->after('deposit_rejection_reason');
            $table->timestamp('deposit_reviewed_at')->nullable()->after('deposit_submitted_at');
            $table->foreignId('deposit_reviewed_by')->nullable()->after('deposit_reviewed_at')
                ->constrained('admins')->nullOnDelete();

            // The admin queue is "deposits waiting on me".
            $table->index(['deposit_status', 'deposit_submitted_at']);
        });

        // The settings page builds its fields from this table, so the rows have
        // to exist for an already-deployed store, not just a freshly seeded one.
        foreach (self::SETTINGS as $setting) {
            DB::table('settings')->insertOrIgnore([
                ...$setting,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['deposit_reviewed_by']);
            $table->dropIndex(['deposit_status', 'deposit_submitted_at']);
            $table->dropColumn([
                'deposit_status', 'deposit_percent', 'deposit_amount',
                'deposit_receipt_path', 'deposit_reference', 'deposit_rejection_reason',
                'deposit_submitted_at', 'deposit_reviewed_at', 'deposit_reviewed_by',
            ]);
        });
    }
};
