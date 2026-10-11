<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('status', 20)->default('pending');          // App\Enums\OrderStatus
            $table->string('payment_method', 20)->default('cod');      // App\Enums\PaymentMethod
            $table->string('payment_status', 20)->default('pending');  // App\Enums\PaymentStatus

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();   // snapshot, survives coupon deletion

            // Shipping address is SNAPSHOTTED onto the order: editing or deleting
            // the saved address later must never rewrite delivery history.
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('alternate_phone', 20)->nullable();
            $table->string('governorate_name');
            $table->string('city_name')->nullable();
            $table->string('street');
            $table->string('building')->nullable();
            $table->string('floor', 50)->nullable();
            $table->string('apartment', 50)->nullable();
            $table->string('landmark')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
