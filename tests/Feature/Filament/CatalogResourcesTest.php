<?php

namespace Tests\Feature\Filament;

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Attributes\RelationManagers\ValuesRelationManager;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->seed(AttributeSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    public function test_every_catalog_page_renders(): void
    {
        $parent = Category::create(['name' => 'ملابس حريمي', 'slug' => 'women']);
        $child = Category::create(['name' => 'فساتين', 'slug' => 'dresses', 'parent_id' => $parent->id]);
        $brand = Brand::create(['name' => 'تريكو مصر', 'slug' => 'treco']);

        $product = Product::create([
            'category_id' => $child->id,
            'brand_id' => $brand->id,
            'name' => 'فستان سواريه',
            'slug' => 'evening-dress',
            'base_price' => 1850,
        ]);
        $product->attributes()->attach(Attribute::where('slug', 'color')->value('id'));

        $variant = $product->variants()->create(['sku' => 'D-1', 'price' => 1850, 'stock_quantity' => 4]);
        $variant->attributeValues()->attach(
            Attribute::where('slug', 'color')->sole()->values()->where('value', 'أحمر')->value('id'),
        );

        $attribute = Attribute::where('slug', 'color')->sole();

        $pages = [
            '/admin/categories' => 'التصنيفات',
            "/admin/categories/{$child->id}/edit" => 'بيانات التصنيف',
            '/admin/categories/create' => 'التصنيف الأب',
            '/admin/brands' => 'الماركات',
            "/admin/brands/{$brand->id}/edit" => 'بيانات الماركة',
            '/admin/attributes' => 'الخصائص',
            "/admin/attributes/{$attribute->id}/edit" => 'بيانات الخصيصة',
            '/admin/product-variants' => 'المتغيّرات',
            "/admin/product-variants/{$variant->id}/edit" => 'المنتج والتركيبة',
            '/admin/product-variants/create' => 'السعر والمخزون',
        ];

        foreach ($pages as $url => $expected) {
            $this->get($url)->assertOk()->assertSee($expected);
        }
    }

    public function test_the_values_relation_manager_lists_the_attributes_values(): void
    {
        $attribute = Attribute::where('slug', 'color')->sole();

        // Relation managers are lazily-loaded Livewire components, so they are
        // driven directly rather than scraped out of the edit page's HTML.
        Livewire::test(ValuesRelationManager::class, [
            'ownerRecord' => $attribute,
            'pageClass' => EditAttribute::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords($attribute->values)
            ->assertSee('أحمر');

        $this->assertCount(10, $attribute->values);
    }

    public function test_a_colour_value_in_use_by_a_variant_is_protected_from_deletion(): void
    {
        $category = Category::create(['name' => 'ت', 'slug' => 'prot']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'ت', 'slug' => 'prot-p', 'base_price' => 10,
        ]);

        $attribute = Attribute::where('slug', 'color')->sole();
        $red = $attribute->values()->where('value', 'أحمر')->sole();

        $variant = $product->variants()->create(['sku' => 'PROT-1', 'price' => 10]);
        $variant->attributeValues()->attach($red->id);

        Livewire::test(ValuesRelationManager::class, [
            'ownerRecord' => $attribute,
            'pageClass' => EditAttribute::class,
        ])->callTableAction('delete', $red);

        // The guard must keep it: the pivot cascades, which would otherwise
        // silently strip the colour out of a live variant.
        $this->assertDatabaseHas('attribute_values', ['id' => $red->id]);
        $this->assertSame(1, $variant->attributeValues()->count());
    }

    public function test_a_category_holding_products_cannot_be_deleted(): void
    {
        $withProducts = Category::create(['name' => 'تيشيرتات', 'slug' => 'tees']);
        Product::create([
            'category_id' => $withProducts->id,
            'name' => 'تيشيرت',
            'slug' => 'tee',
            'base_price' => 200,
        ]);

        $empty = Category::create(['name' => 'فارغ', 'slug' => 'empty']);

        // products.category_id is restrictOnDelete, so the guard must refuse
        // before MySQL raises a foreign key error.
        Livewire::test(ListCategories::class)
            ->callTableAction('delete', $withProducts);

        $this->assertDatabaseHas('categories', ['id' => $withProducts->id]);

        // An empty category still deletes normally.
        Livewire::test(ListCategories::class)
            ->callTableAction('delete', $empty);

        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
    }

    public function test_a_category_cannot_be_its_own_ancestor(): void
    {
        $a = Category::create(['name' => 'أ', 'slug' => 'a']);
        $b = Category::create(['name' => 'ب', 'slug' => 'b', 'parent_id' => $a->id]);
        $c = Category::create(['name' => 'ج', 'slug' => 'c', 'parent_id' => $b->id]);

        // Editing A must not offer B or C (its descendants) as a parent.
        $page = $this->get("/admin/categories/{$a->id}/edit")->assertOk();
        $page->assertOk();

        $descendants = [];
        $queue = [$a->id];
        while ($queue !== []) {
            foreach (Category::where('parent_id', array_shift($queue))->pluck('id') as $id) {
                $descendants[] = $id;
                $queue[] = $id;
            }
        }

        $this->assertEqualsCanonicalizing([$b->id, $c->id], $descendants);
    }

    public function test_variant_stock_state_reflects_the_low_stock_threshold(): void
    {
        $category = Category::create(['name' => 'ت', 'slug' => 'x']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'ت', 'slug' => 'p', 'base_price' => 10,
        ]);

        $out = $product->variants()->create(['sku' => 'A', 'price' => 10, 'stock_quantity' => 0]);
        $low = $product->variants()->create([
            'sku' => 'B', 'price' => 10, 'stock_quantity' => 3, 'low_stock_threshold' => 5,
        ]);
        $ok = $product->variants()->create([
            'sku' => 'C', 'price' => 10, 'stock_quantity' => 50, 'low_stock_threshold' => 5,
        ]);

        $this->assertFalse($out->isInStock());
        $this->assertTrue($low->isLowStock());
        $this->assertFalse($ok->isLowStock());

        // The low-stock table filter must match exactly the one row.
        $lowIds = ProductVariant::query()
            ->where('stock_quantity', '>', 0)
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->pluck('id');

        $this->assertSame([$low->id], $lowIds->all());
    }
}
