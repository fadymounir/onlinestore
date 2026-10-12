<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kalnoy\Nestedset\NestedSet;

/**
 * Turn `categories` into a nested set (kalnoy/nestedset).
 *
 * `_lft`/`_rgt` bound each node's subtree, which is what lets the storefront
 * read a whole branch — any depth — in one query, and what fixes the order of
 * siblings. That makes the old `sort_order` column redundant: position in the
 * tree now *is* the order, maintained from the dashboard's tree page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // parent_id already exists, so only the bounds are added here.
            $table->unsignedInteger(NestedSet::LFT)->default(0)->after('parent_id');
            $table->unsignedInteger(NestedSet::RGT)->default(0)->after(NestedSet::LFT);

            $table->index(NestedSet::getDefaultColumns());
        });

        // Seed the bounds from the order the categories already had, so
        // fixTree() — which reads them via defaultOrder() — keeps siblings
        // in the order the store was arranged in.
        DB::table('categories')->update([
            NestedSet::LFT => DB::raw('`sort_order` + 1'),
        ]);

        Category::fixTree();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('image');
        });

        // Rebuild sort_order from the tree so ordering survives the rollback.
        $position = [];

        foreach (DB::table('categories')->orderBy(NestedSet::LFT)->get(['id', 'parent_id']) as $row) {
            $key = $row->parent_id ?? 0;
            $position[$key] = ($position[$key] ?? -1) + 1;

            DB::table('categories')->where('id', $row->id)->update(['sort_order' => $position[$key]]);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(NestedSet::getDefaultColumns());
            $table->dropColumn([NestedSet::LFT, NestedSet::RGT]);
        });
    }
};
