<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\VariantMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ClothingCatalogSeeder extends Seeder
{
    /** parent => [children] */
    private const CATEGORIES = [
        'ملابس حريمي' => ['فساتين', 'بلوزات وقمصان', 'بناطيل وجيبات', 'عبايات وقفاطين'],
        'ملابس رجالي' => ['قمصان', 'تيشيرتات وبولو', 'بناطيل وجينز', 'جاكيتات وبلوفرات'],
        'ملابس أطفال' => ['ملابس أولاد', 'ملابس بنات'],
        'أحذية' => ['أحذية حريمي', 'أحذية رجالي'],
        'حجاب وطرح' => [],
        'شنط وإكسسوارات' => ['شنط يد', 'أحزمة وكوفيات'],
    ];

    private const BRANDS = [
        'تريكو مصر', 'دار الأناقة', 'قطن المحلة', 'لمسة', 'ميلانو',
        'بيت الحرير', 'كايرو ستايل', 'نسيج',
    ];

    /**
     * [category, name, price, compare_at, variant kind, featured]
     * kind: apparel = لون×مقاس · shoes = لون×مقاس حذاء · single = لون فقط
     */
    private const PRODUCTS = [
        ['فساتين', 'فستان سواريه مطرز بالترتر', 1850, 2400, 'apparel', true],
        ['فساتين', 'فستان كاجوال قطن مشجر', 650, 820, 'apparel', false],
        ['فساتين', 'فستان سهرة مخمل طويل', 2200, null, 'apparel', true],
        ['بلوزات وقمصان', 'بلوزة شيفون بأكمام واسعة', 420, 560, 'apparel', false],
        ['بلوزات وقمصان', 'قميص كتان سادة', 540, null, 'apparel', false],
        ['بناطيل وجيبات', 'بنطلون جينز عالي الخصر', 720, 900, 'apparel', true],
        ['بناطيل وجيبات', 'جيبة ميدي بليسيه', 480, null, 'apparel', false],
        ['عبايات وقفاطين', 'عباية كريب بتطريز يدوي', 1450, 1900, 'apparel', true],
        ['عبايات وقفاطين', 'قفطان مغربي بحزام', 1250, null, 'apparel', false],

        ['قمصان', 'قميص قطن كلاسيك سليم فيت', 580, 750, 'apparel', true],
        ['قمصان', 'قميص كاروهات كاجوال', 520, null, 'apparel', false],
        ['تيشيرتات وبولو', 'تيشيرت قطن 100% سادة', 280, 350, 'apparel', false],
        ['تيشيرتات وبولو', 'بولو شيرت بياقة محبوكة', 390, null, 'apparel', false],
        ['بناطيل وجينز', 'بنطلون جينز ستريتش', 690, 850, 'apparel', true],
        ['بناطيل وجينز', 'بنطلون قماش رسمي', 640, null, 'apparel', false],
        ['جاكيتات وبلوفرات', 'جاكيت جيب شتوي', 1350, 1700, 'apparel', true],
        ['جاكيتات وبلوفرات', 'بلوفر صوف مريلة', 580, null, 'apparel', false],

        ['ملابس أولاد', 'طقم أولاد تيشيرت وشورت', 360, 450, 'apparel', false],
        ['ملابس بنات', 'فستان بنات مناسبات', 520, null, 'apparel', true],

        ['أحذية حريمي', 'حذاء كعب عالي جلد', 1150, 1450, 'shoes', true],
        ['أحذية حريمي', 'باليرينا جلد طبيعي', 680, null, 'shoes', false],
        ['أحذية رجالي', 'حذاء كلاسيك جلد طبيعي', 1480, 1850, 'shoes', true],
        ['أحذية رجالي', 'سنيكرز رياضي مريح', 920, null, 'shoes', false],

        ['حجاب وطرح', 'طرحة شيفون سادة', 120, 160, 'single', false],
        ['حجاب وطرح', 'إيشارب حرير مطبوع', 260, null, 'single', false],

        ['شنط يد', 'شنطة يد جلد بحزام كتف', 980, 1250, 'single', true],
        ['شنط يد', 'شنطة ظهر كانفاس', 540, null, 'single', false],
        ['أحزمة وكوفيات', 'حزام جلد طبيعي بإبزيم معدني', 320, null, 'single', false],
    ];

    private const DESCRIPTIONS = [
        'خامة مريحة تتحمل الغسيل المتكرر دون أن تفقد شكلها، ومناسبة للجو المصري على مدار السنة.',
        'تصميم عملي يصلح للخروج اليومي والمناسبات، بقَصّة مدروسة تناسب معظم المقاسات.',
        'قطعة أساسية في الدولاب: سهلة التنسيق مع أي لون، وخامتها لا تحتاج كي كثير.',
        'شغل وتشطيب نظيف من الداخل والخارج، وخياطة مزدوجة عند الأطراف تطيل عمر القطعة.',
    ];

    public function run(): void
    {
        $this->seedShoeSizes();

        $categories = $this->seedCategories();
        $brands = collect(self::BRANDS)->map(fn (string $name, int $i) => Brand::updateOrCreate(
            ['slug' => 'brand-'.($i + 1)],
            ['name' => $name, 'is_active' => true],
        ));

        $color = Attribute::where('slug', 'color')->sole();
        $size = Attribute::where('slug', 'size')->sole();
        $shoeSize = Attribute::where('slug', 'shoe-size')->sole();

        foreach (self::PRODUCTS as $i => [$categoryName, $name, $price, $compareAt, $kind, $featured]) {
            $category = $categories[$categoryName] ?? $categories['حجاب وطرح'];

            $product = Product::updateOrCreate(
                ['slug' => 'product-'.($i + 1)],
                [
                    'category_id' => $category->id,
                    'brand_id' => $brands[$i % $brands->count()]->id,
                    'name' => $name,
                    'short_description' => self::DESCRIPTIONS[$i % count(self::DESCRIPTIONS)],
                    'description' => '<p>'.self::DESCRIPTIONS[$i % count(self::DESCRIPTIONS)].'</p>'
                        .'<ul><li>الخامة: قطن مخلوط عالي الجودة</li>'
                        .'<li>العناية: غسيل على 30 درجة، بدون مُبيّض</li>'
                        .'<li>بلد الصنع: مصر</li></ul>',
                    'base_price' => $price,
                    'compare_at_price' => $compareAt,
                    'is_active' => true,
                    'is_featured' => $featured,
                    'published_at' => now()->subDays(60 - $i),
                ],
            );

            // Which attributes this product varies by.
            $attributeIds = match ($kind) {
                'apparel' => [$color->id, $size->id],
                'shoes' => [$color->id, $shoeSize->id],
                'single' => [$color->id],
            };
            $product->attributes()->sync($attributeIds);

            $this->seedVariants($product, $kind, $i, $color, $size, $shoeSize);
            $this->seedImages($product, $i);
        }
    }

    /** Shoes need their own numeric size scale, not S/M/L. */
    private function seedShoeSizes(): void
    {
        $attribute = Attribute::updateOrCreate(
            ['slug' => 'shoe-size'],
            ['name' => 'مقاس الحذاء', 'type' => AttributeType::Select, 'sort_order' => 2, 'is_active' => true],
        );

        foreach (range(37, 45) as $j => $number) {
            AttributeValue::updateOrCreate(
                ['attribute_id' => $attribute->id, 'value' => (string) $number],
                ['sort_order' => $j],
            );
        }
    }

    /** @return array<string, Category> keyed by Arabic name */
    private function seedCategories(): array
    {
        $map = [];
        $order = 0;

        foreach (self::CATEGORIES as $parentName => $children) {
            $parent = Category::updateOrCreate(
                ['slug' => 'cat-'.++$order],
                ['name' => $parentName, 'parent_id' => null, 'sort_order' => $order, 'is_active' => true],
            );
            $map[$parentName] = $parent;

            foreach ($children as $childName) {
                $child = Category::updateOrCreate(
                    ['slug' => 'cat-'.++$order],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'sort_order' => $order,
                        'is_active' => true,
                    ],
                );
                $map[$childName] = $child;
            }
        }

        return $map;
    }

    private function seedVariants(
        Product $product,
        string $kind,
        int $index,
        Attribute $color,
        Attribute $size,
        Attribute $shoeSize,
    ): void {
        // Rotate through the palette so products don't all share the same colours.
        $colors = $color->values()->orderBy('sort_order')->pluck('id')
            ->slice($index % 4, 3)->values()->all();

        if ($colors === []) {
            $colors = $color->values()->orderBy('sort_order')->pluck('id')->take(3)->all();
        }

        $second = match ($kind) {
            'apparel' => $size->values()->whereIn('value', ['S', 'M', 'L', 'XL'])->pluck('id')->all(),
            'shoes' => $shoeSize->values()->whereIn('value', ['39', '40', '41', '42'])->pluck('id')->all(),
            'single' => [],
        };

        $combinations = VariantMatrix::combinations(array_values(array_filter([$colors, $second])));
        $prefix = 'P'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

        foreach ($combinations as $n => $combination) {
            $sku = VariantMatrix::sku($prefix, $combination);

            $variant = $product->variants()->updateOrCreate(
                ['sku' => $sku],
                [
                    // Larger sizes carry a small uplift, as a real catalogue would.
                    'price' => $product->base_price + ($n % 4) * 25,
                    'compare_at_price' => $product->compare_at_price,
                    'cost_price' => round((float) $product->base_price * 0.55, 2),
                    'stock_quantity' => [0, 3, 12, 25, 40, 7][$n % 6],
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                ],
            );

            $variant->attributeValues()->sync($combination);
        }
    }

    /**
     * Writes real JPEGs so the panel and the storefront show actual images
     * instead of broken thumbnails. GD is compiled into the app image.
     */
    private function seedImages(Product $product, int $index): void
    {
        $palette = [
            [206, 161, 123], [120, 134, 158], [168, 120, 128], [112, 142, 128],
            [148, 136, 170], [190, 168, 120], [130, 130, 142], [172, 142, 122],
        ];

        $disk = Storage::disk('public');

        foreach (range(0, 1) as $n) {
            $path = "products/demo-{$product->id}-{$n}.jpg";

            if (! $disk->exists($path)) {
                [$r, $g, $b] = $palette[($index + $n) % count($palette)];

                $image = imagecreatetruecolor(900, 1200);

                // Soft vertical gradient — enough to read as a product card.
                for ($y = 0; $y < 1200; $y++) {
                    $shade = 1 - ($y / 1200) * 0.35;
                    $line = imagecolorallocate(
                        $image,
                        (int) ($r * $shade),
                        (int) ($g * $shade),
                        (int) ($b * $shade),
                    );
                    imageline($image, 0, $y, 900, $y, $line);
                }

                ob_start();
                imagejpeg($image, null, 82);
                $binary = ob_get_clean();
                imagedestroy($image);

                $disk->put($path, $binary);
            }

            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'path' => $path],
                [
                    'alt' => $product->name,
                    'sort_order' => $n,
                    'is_primary' => $n === 0,
                ],
            );
        }
    }
}
