<?php

namespace Tests\Feature\Shop;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, Category> root, child, grandchild, leaf */
    private function fourLevels(): array
    {
        $root = Category::create(['name' => 'ملابس حريمي', 'slug' => 'women']);
        $child = Category::create(['name' => 'فساتين', 'slug' => 'dresses', 'parent_id' => $root->id]);
        $grandchild = Category::create(['name' => 'سواريه', 'slug' => 'evening', 'parent_id' => $child->id]);
        $leaf = Category::create(['name' => 'سواريه طويل', 'slug' => 'long-evening', 'parent_id' => $grandchild->id]);

        return [$root->fresh(), $child->fresh(), $grandchild->fresh(), $leaf->fresh()];
    }

    private function publish(Category $category, string $slug): Product
    {
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => Brand::firstOrCreate(['slug' => 'b'], ['name' => 'ماركة'])->id,
            'name' => 'منتج '.$slug,
            'slug' => $slug,
            'base_price' => 100,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $product->variants()->create(['sku' => strtoupper($slug), 'price' => 100, 'stock_quantity' => 5]);

        return $product;
    }

    public function test_the_menu_shows_every_level_of_the_tree(): void
    {
        $this->fourLevels();

        $this->get('/')
            ->assertOk()
            ->assertSee('ملابس حريمي')
            ->assertSee('فساتين')
            ->assertSee('سواريه')
            ->assertSee('سواريه طويل');
    }

    public function test_the_menu_links_each_category_to_its_own_page(): void
    {
        [$root, , , $leaf] = $this->fourLevels();

        $this->get('/')
            ->assertOk()
            ->assertSee(route('category', $root), escape: false)
            ->assertSee(route('category', $leaf), escape: false);
    }

    public function test_a_switched_off_branch_disappears_from_the_menu(): void
    {
        [, $child] = $this->fourLevels();

        $child->update(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('ملابس حريمي')
            // The child is off, so its own descendants go with it.
            ->assertDontSee('سواريه طويل');
    }

    public function test_a_category_page_lists_products_from_its_whole_subtree(): void
    {
        [$root, $child, $grandchild, $leaf] = $this->fourLevels();

        $this->publish($leaf, 'deep');
        $this->publish($child, 'mid');

        $this->get(route('category', $root))
            ->assertOk()
            ->assertSee('منتج deep')
            ->assertSee('منتج mid');

        $this->get(route('category', $grandchild))
            ->assertOk()
            ->assertSee('منتج deep')
            ->assertDontSee('منتج mid');
    }

    public function test_a_category_page_shows_the_full_breadcrumb_trail(): void
    {
        [$root, $child, $grandchild, $leaf] = $this->fourLevels();

        $this->get(route('category', $leaf))
            ->assertOk()
            ->assertSee(route('category', $root), escape: false)
            ->assertSee(route('category', $child), escape: false)
            ->assertSee(route('category', $grandchild), escape: false);
    }

    public function test_a_category_under_a_switched_off_ancestor_is_not_reachable(): void
    {
        [$root, , , $leaf] = $this->fourLevels();

        $root->update(['is_active' => false]);

        $this->get(route('category', $leaf))->assertNotFound();
    }
}
