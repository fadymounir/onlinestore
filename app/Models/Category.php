<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * A category tree of unlimited depth.
 *
 * `parent_id` holds the structure; `path` and `depth` are derived caches the
 * model keeps in sync on every write, so a subtree can be read with one query
 * instead of one query per level.
 */
class Category extends Model
{
    /** Guards against a corrupt tree sending the recursive sync into a loop. */
    private const MAX_DEPTH = 20;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image', 'sort_order', 'is_active',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'depth' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'depth' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (! $category->canMoveTo($category->parent_id)) {
                throw new InvalidArgumentException(
                    'A category cannot be placed under itself or one of its own descendants.'
                );
            }
        });

        // `path` contains the row's own id, so it can only be written once the
        // insert has produced one.
        static::created(fn (self $category) => $category->refreshSubtreePath());

        static::updated(function (self $category): void {
            if ($category->wasChanged('parent_id')) {
                $category->refreshSubtreePath();
            }
        });
    }

    /*
    |---------------------------------------------------------------------------
    | Relations
    |---------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

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

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Sibling order as the dashboard arranged it, name as a stable tiebreaker. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Everything beneath $category, at any depth, plus $category itself. */
    public function scopeInSubtreeOf(Builder $query, self $category): Builder
    {
        return $query->where('path', 'like', $category->subtreePathPattern());
    }

    /*
    |---------------------------------------------------------------------------
    | Tree reading
    |---------------------------------------------------------------------------
    */

    /**
     * The whole tree as root categories with `children` nested all the way down,
     * built in memory from a single query.
     *
     * A category whose parent is excluded is excluded with it, so switching off
     * one branch hides everything under it.
     *
     * @return Collection<int, self>
     */
    public static function tree(bool $onlyActive = false): Collection
    {
        $categories = static::query()
            ->when($onlyActive, fn (Builder $query) => $query->active())
            ->ordered()
            ->get();

        return static::nest($categories);
    }

    /**
     * Nest a flat set of categories onto their parents. Keys are cast to string
     * because collection grouping does not preserve integer key types.
     *
     * @param  Collection<int, self>  $categories
     * @return Collection<int, self> the roots of the resulting tree
     */
    public static function nest(Collection $categories): Collection
    {
        $byParent = $categories->groupBy(fn (self $category) => (string) $category->parent_id);

        foreach ($categories as $category) {
            $category->setRelation(
                'children',
                $byParent->get((string) $category->getKey(), new Collection)->values(),
            );
        }

        return $byParent->get('', new Collection)->values();
    }

    /** Ids of this category and every descendant — one query, any depth. */
    public function subtreeIds(): array
    {
        if (blank($this->path)) {
            return [$this->getKey()];
        }

        return static::query()
            ->inSubtreeOf($this)
            ->pluck('id')
            ->all();
    }

    /** Root-first ancestors, for breadcrumbs. */
    public function ancestors(): Collection
    {
        $ids = $this->ancestorIds();

        if ($ids === []) {
            return new Collection;
        }

        return static::query()
            ->whereKey($ids)
            ->orderBy('depth')
            ->get();
    }

    /** @return array<int, int> ids of every category above this one */
    public function ancestorIds(): array
    {
        $ids = array_map('intval', array_filter(explode('/', (string) $this->path), 'strlen'));

        return array_values(array_filter($ids, fn (int $id) => $id !== $this->getKey()));
    }

    /** @return array<int, int> ids of every category beneath this one */
    public function descendantIds(): array
    {
        return array_values(array_filter(
            $this->subtreeIds(),
            fn (int $id) => $id !== $this->getKey(),
        ));
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
            ->push($this)
            ->pluck('name')
            ->implode($separator);
    }

    /**
     * May this category sit under $parentId? False for itself and for anything
     * already inside its own subtree, which would detach the branch from the
     * tree and loop the path sync.
     */
    public function canMoveTo(int | string | null $parentId): bool
    {
        if (blank($parentId)) {
            return true;
        }

        $parentId = (int) $parentId;

        if (! $this->exists) {
            return true;
        }

        return $parentId !== (int) $this->getKey()
            && ! in_array($parentId, $this->descendantIds(), true);
    }

    private function subtreePathPattern(): string
    {
        return $this->path.'%';
    }

    /**
     * Recompute `path` and `depth` for this category and push the change down
     * the subtree. Writes go straight to the query builder: saving the model
     * would re-fire `updated` and recurse.
     */
    public function refreshSubtreePath(int $guard = 0): void
    {
        if ($guard > self::MAX_DEPTH) {
            return;
        }

        $parent = $this->parent_id
            ? static::query()->find($this->parent_id)
            : null;

        $path = ($parent?->path ?? '/').$this->getKey().'/';
        $depth = $parent ? $parent->depth + 1 : 0;

        if ($this->path === $path && $this->depth === $depth) {
            // Unchanged here means unchanged below, so stop walking.
            return;
        }

        static::query()->whereKey($this->getKey())->update([
            'path' => $path,
            'depth' => $depth,
        ]);

        $this->path = $path;
        $this->depth = $depth;
        $this->syncOriginalAttributes(['path', 'depth']);

        $this->children()->get()->each(fn (self $child) => $child->refreshSubtreePath($guard + 1));
    }
}
