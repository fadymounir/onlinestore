<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Materialised path — the slash-wrapped id chain from the root down
            // to this row, e.g. "/3/14/27/". Lets one indexed LIKE fetch a whole
            // subtree, which is what the storefront needs for "products under
            // this category and everything beneath it".
            $table->string('path')->nullable()->after('parent_id');
            // 0 for a root category, 1 for its child, and so on. Derived from
            // `path`, stored so the tree can be sorted and indented in SQL.
            $table->unsignedTinyInteger('depth')->default(0)->after('path');

            $table->index('path');
            $table->index(['depth', 'sort_order']);
        });

        $this->backfill(parentId: null, parentPath: '/', depth: 0);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['path']);
            $table->dropIndex(['depth', 'sort_order']);
            $table->dropColumn(['path', 'depth']);
        });
    }

    /** Walk the existing rows top-down and stamp path/depth on each one. */
    private function backfill(?int $parentId, string $parentPath, int $depth): void
    {
        $rows = DB::table('categories')
            ->when($parentId === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parentId),
            )
            ->orderBy('id')
            ->pluck('id');

        foreach ($rows as $id) {
            $path = $parentPath.$id.'/';

            DB::table('categories')->where('id', $id)->update([
                'path' => $path,
                'depth' => $depth,
            ]);

            $this->backfill($id, $path, $depth + 1);
        }
    }
};
