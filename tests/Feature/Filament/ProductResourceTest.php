<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AttributeSeeder;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->seed(AttributeSeeder::class);

        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    private function clothingProduct(): Product
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'تيشيرتات', 'slug' => 't-shirts'])->id,
            'name' => 'تيشيرت قطن',
            'slug' => 'cotton-tee',
            'base_price' => 250,
        ]);

        $product->attributes()->attach(Attribute::whereIn('slug', ['color', 'size'])->pluck('id'));

        return $product;
    }

    public function test_the_generator_builds_every_colour_and_size_combination(): void
    {
        $product = $this->clothingProduct();

        $color = Attribute::where('slug', 'color')->sole();
        $size = Attribute::where('slug', 'size')->sole();

        $colorIds = $color->values()->whereIn('value', ['أحمر', 'أسود', 'أبيض'])->pluck('id')->all();
        $sizeIds = $size->values()->whereIn('value', ['M', 'L', 'XL'])->pluck('id')->all();

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])->callAction('generateMatrix', data: [
            "values_{$color->id}" => $colorIds,
            "values_{$size->id}" => $sizeIds,
            'sku_prefix' => 'TEE',
            'price' => 299,
            'stock_quantity' => 7,
        ]);

        // 3 colours x 3 sizes
        $this->assertSame(9, $product->variants()->count());

        $variant = $product->variants()->with('attributeValues')->first();
        $this->assertCount(2, $variant->attributeValues);
        $this->assertSame('299.00', $variant->price);
        $this->assertSame(7, $variant->stock_quantity);
        $this->assertStringStartsWith('TEE-', $variant->sku);

        // Every combination is distinct.
        $labels = $product->variants()->with('attributeValues')->get()
            ->map(fn (ProductVariant $v) => $v->label())->all();
        $this->assertCount(9, array_unique($labels));
    }

    public function test_running_the_generator_again_adds_only_the_new_combinations(): void
    {
        $product = $this->clothingProduct();

        $color = Attribute::where('slug', 'color')->sole();
        $size = Attribute::where('slug', 'size')->sole();

        $red = $color->values()->where('value', 'أحمر')->pluck('id')->all();
        $black = $color->values()->where('value', 'أسود')->pluck('id')->all();
        $sizes = $size->values()->whereIn('value', ['M', 'L'])->pluck('id')->all();

        $run = fn (array $colors) => Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])->callAction('generateMatrix', data: [
            "values_{$color->id}" => $colors,
            "values_{$size->id}" => $sizes,
            'sku_prefix' => 'TEE',
            'price' => 299,
            'stock_quantity' => 7,
        ]);

        $run($red);
        $this->assertSame(2, $product->variants()->count());

        // Re-running with the same ticks must not duplicate anything.
        $run($red);
        $this->assertSame(2, $product->variants()->count());

        // Adding a colour adds only its two sizes.
        $run([...$red, ...$black]);
        $this->assertSame(4, $product->variants()->count());
        $this->assertSame(4, ProductVariant::distinct('sku')->count('sku'));
    }

    public function test_the_product_pages_render(): void
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'تيشيرتات', 'slug' => 't-shirts'])->id,
            'name' => 'تيشيرت قطن',
            'slug' => 'cotton-tee',
            'base_price' => 250,
        ]);

        $this->get('/admin/products')->assertOk()->assertSee('المنتجات');
        $this->get('/admin/products/create')->assertOk()->assertSee('بيانات المنتج');

        // The edit page is where the variants relation manager lives.
        $this->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('السعر والعرض')
            ->assertSee('يتغيّر هذا المنتج حسب');
    }
}
