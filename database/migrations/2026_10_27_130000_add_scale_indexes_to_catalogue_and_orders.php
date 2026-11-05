<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the sort and filter columns the storefront and the dashboard lean on.
 *
 * The original indexes cover the lookups (a product by category, an order by
 * customer) but not the orderings, so at six figures of rows MySQL was reading
 * the whole matching set and sorting it: the catalogue's default "newest first"
 * filesorted ~49K published products per page, and the dashboard's latest-orders
 * widget scanned all 100K orders to show ten.
 *
 * Deliberately not indexed: the catalogue's "in stock only" filter. MySQL runs
 * it as a LooseScan semi-join across every variant, so a (product_id,
 * stock_quantity) index is used but saves ~3% — that filter needs a stock
 * column denormalised onto products, not an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Catalogue default sort: published() narrows on is_active and
            // published_at, then orders by published_at — one index does all three.
            $table->index(['is_active', 'published_at'], 'products_active_published_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            // "Latest orders", and the customer's own order history.
            $table->index('placed_at', 'orders_placed_at_index');

            // Revenue is delivered money over a date window: the existing
            // (status, created_at) index stops being useful once the range is
            // on delivered_at instead.
            $table->index(['status', 'delivered_at'], 'orders_status_delivered_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_published_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_placed_at_index');
            $table->dropIndex('orders_status_delivered_at_index');
        });
    }
};
