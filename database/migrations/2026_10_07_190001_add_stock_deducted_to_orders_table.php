<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Checkout deducts stock and sets this. Cancelling or returning an
            // order restores stock ONLY when it is true, so an order whose
            // stock was never taken (e.g. seeded history) can't inflate it.
            $table->boolean('stock_deducted')->default(false)->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stock_deducted');
        });
    }
};
