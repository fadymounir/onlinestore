<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order can now be placed without a delivery address.
 *
 * With `customer_addresses_enabled` off the shop collects delivery details
 * itself — by phone, by WhatsApp — so checkout stops asking. The order still
 * carries who to contact (`recipient_name`, `phone`, taken from the account),
 * but the place it goes to is not known yet, so the two columns that spelled
 * it out have to accept null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('governorate_name')->nullable()->change();
            $table->string('street')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('governorate_name')->nullable(false)->change();
            $table->string('street')->nullable(false)->change();
        });
    }
};
