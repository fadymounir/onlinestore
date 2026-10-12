<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\Collection as NodeCollection;
use Kalnoy\Nestedset\NodeTrait;
use Kalnoy\Nestedset\QueryBuilder;

/**
 * A category tree of unlimited depth, stored as a nested set.
 *
 * `parent_id` holds the structure; `_lft`/`_rgt` bound each node's subtree,
 * which is what lets a whole branch — at any depth — be read with one query and
 * what fixes the order of siblings. All of that is maintained by
 * kalnoy/nestedset: move nodes with its API (appendToNode, makeRoot, up, down)
 * rather than by writing `parent_id` directly.
 *
 * @method static QueryBuilder query()
 * @method static QueryBuilder whereDescendantOrSelf(mixed $id)
 * @method static int fixTree(mixed $root = null)
 */
class Category extends Model
{
    use NodeTrait;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image', 'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /*
    |---------------------------------------------------------------------------
    | Relations
    |---------------------------------------------------------------------------
    */

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /*
    |---------------------------------------------------------------------------
    | Tree reading
    |---------------------------------------------------------------------------
    */

    /**
     * The whole tree — root categories with `children` nested all the way down —
     * from a single query, each node carrying a `depth` attribute.
     *
     * A category whose parent is excluded is excluded with it, so switching one
     * branch off hides everything under it.
     *
     * @return NodeCollection<int, self>
     */
    public static function tree(bool $onlyActive = false): NodeCollection
    {
        return static::query()
            ->when($onlyActive, fn (Builder $query) => $query->active())
            ->withDepth()
            ->defaultOrder()
            ->get()
            // Explicit null root: with an `active` filter the least-lft node is
            // not guaranteed to be a root, and toTree()'s guess would then
            // promote a mid-tree node's children to the top level.
            ->toTree(null);
    }

    /** Ids of this category and every descendant — one query, any depth. */
    public function subtreeIds(): array
    {
        return static::query()
            ->whereDescendantOrSelf($this)
            ->pluck('id')
            ->all();
    }

    /** @return array<int, int> ids of every category beneath this one */
    public function descendantIds(): array
    {
        return static::query()
            ->whereDescendantOf($this)
            ->pluck('id')
            ->all();
    }

    /** Products sitting anywhere in this subtree — blocks deletion. */
    public function subtreeProductsCount(): int
    {
        return Product::whereIn('category_id', $this->subtreeIds())->count();
    }

    /** "ملابس حريمي ← فساتين ← سواريه" — readable position in the tree. */
    public function breadcrumbLabel(string $separator = ' ← '): string
    {
        return $this->ancestors()
            ->defaultOrder()
            ->pluck('name')
            ->push($this->name)
            ->implode($separator);
    }

    /**
     * May this category sit under $parentId? False for itself and for anything
     * already inside its own subtree, which would detach the branch.
     */
    public function canMoveTo(int|string|null $parentId): bool
    {
        if (blank($parentId) || ! $this->exists) {
            return true;
        }

        $parentId = (int) $parentId;

        return $parentId !== (int) $this->getKey()
            && ! in_array($parentId, $this->descendantIds(), true);
    }
}
