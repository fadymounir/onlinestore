<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giving money back. A cancelled deposit order — or a returned one the courier
 * already collected for — leaves the store holding the customer's money, and
 * nothing in the schema said whether it had been sent back. These columns are
 * that record: how much went out, on which transfer, by whose hand, and when.
 *
 * The amount is stored rather than recomputed: a refund can be less than what
 * was taken (a restocking cut), and the figure has to survive any later change
 * to the order's totals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->nullable()->after('deposit_reviewed_by');
            $table->string('refund_reference')->nullable()->after('refund_amount');
            $table->string('refund_note')->nullable()->after('refund_reference');
            $table->timestamp('refunded_at')->nullable()->after('refund_note');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')
                ->constrained('admins')->nullOnDelete();

            // The admin queue is "orders that owe money back": cancelled or
            // returned, something was paid, nothing refunded yet.
            $table->index(['refunded_at', 'status', 'payment_status'], 'orders_refund_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropIndex('orders_refund_queue_index');
            $table->dropColumn([
                'refund_amount', 'refund_reference', 'refund_note',
                'refunded_at', 'refunded_by',
            ]);
        });
    }
};
