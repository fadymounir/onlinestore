<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categories\Pages\ManageCategoryTree;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    /** Four levels: root -> child -> grandchild -> great-grandchild. */
    private function fourLevels(): array
    {
        $root = Category::create(['name' => 'ملابس حريمي', 'slug' => 'women']);
        $child = Category::create(['name' => 'فساتين', 'slug' => 'dresses', 'parent_id' => $root->id]);
        $grandchild = Category::create(['name' => 'سواريه', 'slug' => 'evening', 'parent_id' => $child->id]);
        $leaf = Category::create(['name' => 'سواريه طويل', 'slug' => 'long-evening', 'parent_id' => $grandchild->id]);

        return [$root->fresh(), $child->fresh(), $grandchild->fresh(), $leaf->fresh()];
    }

    public function test_the_tree_page_renders_every_level(): void
    {
        $this->fourLevels();

        $this->get('/admin/categories')
            ->assertOk()
            ->assertSee('ملابس حريمي')
            ->assertSee('فساتين')
            ->assertSee('سواريه')
            ->assertSee('سواريه طويل');
    }

    public function test_the_flat_table_view_is_still_reachable(): void
    {
        $this->fourLevels();

        $this->get('/admin/categories/list')
            ->assertOk()
            ->assertSee('ملابس حريمي')
            ->assertSee('سواريه طويل');
    }

    public function test_a_child_can_be_added_under_any_node_at_any_depth(): void
    {
        [, , , $leaf] = $this->fourLevels();

        Livewire::test(ManageCategoryTree::class)
            ->callAction('createChild', [
                'name' => 'سواريه مطرز',
                'slug' => 'embroidered-evening',
                'is_active' => true,
            ], ['category' => $leaf->id])
            ->assertHasNoActionErrors();

        $created = Category::where('slug', 'embroidered-evening')->sole();

        $this->assertSame($leaf->id, $created->parent_id);
        // Fifth level: the tree is not capped at a fixed depth.
        $this->assertSame(4, $created->ancestors()->count());
    }

    public function test_a_root_category_can_be_created_from_the_page_header(): void
    {
        Livewire::test(ManageCategoryTree::class)
            ->callAction('createRoot', ['name' => 'أحذية', 'slug' => 'shoes', 'is_active' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue(Category::where('slug', 'shoes')->sole()->isRoot());
    }

    public function test_moving_a_branch_carries_its_descendants(): void
    {
        [$root, $child, $grandchild, $leaf] = $this->fourLevels();
        $other = Category::create(['name' => 'أحذية', 'slug' => 'shoes']);

        Livewire::test(ManageCategoryTree::class)
            ->callAction('move', ['parent_id' => $other->id], ['category' => $child->id]);

        $this->assertSame($other->id, $child->fresh()->parent_id);
        // The subtree moved with it rather than being orphaned under the root.
        $this->assertEqualsCanonicalizing(
            [$child->id, $grandchild->id, $leaf->id],
            $other->fresh()->descendantIds(),
        );
        $this->assertSame([], $root->fresh()->descendantIds());
    }

    public function test_a_branch_cannot_be_moved_into_its_own_descendant(): void
    {
        [, $child, $grandchild] = $this->fourLevels();

        Livewire::test(ManageCategoryTree::class)
            ->callAction('move', ['parent_id' => $grandchild->id], ['category' => $child->id]);

        $this->assertNotSame($grandchild->id, $child->fresh()->parent_id);
        $this->assertFalse($child->fresh()->canMoveTo($grandchild->id));
    }

    public function test_siblings_can_be_reordered(): void
    {
        $first = Category::create(['name' => 'أ', 'slug' => 'a']);
        $second = Category::create(['name' => 'ب', 'slug' => 'b']);

        $this->assertSame(['أ', 'ب'], Category::tree()->pluck('name')->all());

        Livewire::test(ManageCategoryTree::class)
            ->callAction('moveDown', arguments: ['category' => $first->id]);

        $this->assertSame(['ب', 'أ'], Category::tree()->pluck('name')->all());

        Livewire::test(ManageCategoryTree::class)
            ->callAction('moveUp', arguments: ['category' => $first->id]);

        $this->assertSame(['أ', 'ب'], Category::tree()->pluck('name')->all());

        $this->assertNotNull($second);
    }

    public function test_switching_a_branch_off_hides_it_and_everything_under_it(): void
    {
        [$root, $child] = $this->fourLevels();

        Livewire::test(ManageCategoryTree::class)
            ->callAction('toggleActive', arguments: ['category' => $root->id]);

        $this->assertFalse($root->fresh()->is_active);
        // The child stays active in its own right, but the storefront tree
        // drops the whole branch because its parent is gone from the set.
        $this->assertTrue($child->fresh()->is_active);
        $this->assertCount(0, Category::tree(onlyActive: true));
    }

    public function test_deleting_a_node_is_refused_while_its_subtree_holds_products(): void
    {
        [$root, , , $leaf] = $this->fourLevels();

        Product::create([
            'category_id' => $leaf->id, 'name' => 'فستان', 'slug' => 'dress', 'base_price' => 100,
        ]);

        Livewire::test(ManageCategoryTree::class)
            ->callAction('delete', arguments: ['category' => $root->id]);

        $this->assertDatabaseHas('categories', ['id' => $root->id]);
    }

    public function test_deleting_an_empty_branch_removes_its_descendants(): void
    {
        [$root, $child, $grandchild, $leaf] = $this->fourLevels();

        Livewire::test(ManageCategoryTree::class)
            ->callAction('delete', arguments: ['category' => $child->id]);

        $this->assertDatabaseMissing('categories', ['id' => $child->id]);
        $this->assertDatabaseMissing('categories', ['id' => $grandchild->id]);
        $this->assertDatabaseMissing('categories', ['id' => $leaf->id]);
        $this->assertDatabaseHas('categories', ['id' => $root->id]);
    }

    public function test_the_tree_stays_consistent_after_a_round_of_edits(): void
    {
        [$root, $child, $grandchild] = $this->fourLevels();
        $other = Category::create(['name' => 'أحذية', 'slug' => 'shoes']);

        Livewire::test(ManageCategoryTree::class)
            ->callAction('move', ['parent_id' => $other->id], ['category' => $grandchild->id])
            ->callAction('moveUp', arguments: ['category' => $other->id])
            ->callAction('edit', [
                'name' => 'فساتين وعبايات',
                'slug' => 'dresses',
                'is_active' => true,
            ], ['category' => $child->id]);

        $this->assertFalse(Category::isBroken());
        $this->assertSame('فساتين وعبايات', $child->fresh()->name);
        $this->assertSame($other->id, $grandchild->fresh()->parent_id);
        $this->assertSame($root->id, $child->fresh()->parent_id);
    }
}
