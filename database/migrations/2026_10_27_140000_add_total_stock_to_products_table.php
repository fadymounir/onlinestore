<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalises each product's stock onto the product row.
 *
 * The catalogue's "in stock only" filter was an EXISTS over product_variants,
 * which MySQL plans as a LooseScan semi-join across every variant in the
 * table — no index on the variants side fixes that shape, so the stock has to
 * live where the filter runs. ProductVariantObserver keeps the column true.
 *
 * It is a plain SUM over all of a product's variants, matching what
 * Product::totalStock() returned when it asked the database each time.
 *
 * No index of its own: the filter always runs with the published() scope and
 * the newest-first sort, and MySQL prefers products_active_published_index for
 * the ordering and checks total_stock as it scans. An (is_active, total_stock,
 * published_at) index was measured here and the optimiser never chose it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('total_stock')->default(0)->after('compare_at_price');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('total_stock');
        });
    }

    /** Correlated rather than a join, so the statement runs on MySQL and SQLite alike. */
    private function backfill(): void
    {
        DB::table('products')->update([
            'total_stock' => DB::raw(
                '(select coalesce(sum(stock_quantity), 0) from product_variants'
                .' where product_variants.product_id = products.id)'
            ),
        ]);
    }
};
