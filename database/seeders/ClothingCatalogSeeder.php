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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ClothingCatalogSeeder extends Seeder
{
    /**
     * The category tree, nested as deeply as you like: name => [children].
     * A leaf is an empty array. Four levels here, to exercise the mega menu.
     */
    private const CATEGORIES = [
        'ملابس حريمي' => [
            'فساتين' => [
                'فساتين سواريه' => [
                    'سواريه طويل' => [],
                    'سواريه قصير' => [],
                ],
                'فساتين كاجوال' => [],
            ],
            'بلوزات وقمصان' => [],
            'بناطيل وجيبات' => [],
            'عبايات وقفاطين' => [],
        ],
        'ملابس رجالي' => [
            'قمصان' => [],
            'تيشيرتات وبولو' => [],
            'بناطيل وجينز' => [],
            'جاكيتات وبلوفرات' => [],
        ],
        'ملابس أطفال' => [
            'ملابس أولاد' => [],
            'ملابس بنات' => [],
        ],
        'أحذية' => [
            'أحذية حريمي' => [
                'كعب عالي' => [],
                'باليرينا وفلات' => [],
            ],
            'أحذية رجالي' => [
                'أحذية كلاسيك' => [],
                'سنيكرز' => [],
            ],
        ],
        'حجاب وطرح' => [],
        'شنط وإكسسوارات' => [
            'شنط يد' => [],
            'أحزمة وكوفيات' => [],
        ],
    ];

    /**
     * Category name => curated Unsplash photo ids, best first.
     *
     * Hand-picked rather than keyword-searched: open image APIs return
     * whatever matches the word, which for a clothing shop means the odd
     * museum exhibit or holiday snap. The first id is the category's own
     * image; products in that category cycle through the rest.
     */
    private const CATEGORY_PHOTOS = [
        'ملابس حريمي' => ['photo-1623609163859-ca93c959b98a', 'photo-1490481651871-ab68de25d43d', 'photo-1567401893414-76b7b1e5a7a5', 'photo-1532453288672-3a27e9be9efd'],
        'فساتين' => ['flagged/photo-1585052201332-b8c0ce30972f', 'photo-1612336307429-8a898d10e223', 'photo-1550639525-c97d455acf70', 'photo-1595777457583-95e059d581b8', 'photo-1618932260643-eee4a2f652a6'],
        'فساتين سواريه' => ['photo-1623580674393-edf6eb7090f8', 'photo-1610209740880-6ecc4b20ea78', 'photo-1547697933-66bcb20f114a'],
        'سواريه طويل' => ['photo-1763959952596-1512ebea8eb1', 'photo-1775036190849-16ef6dc9519e', 'photo-1710023141893-5ccbe1c4ae6d'],
        'سواريه قصير' => ['photo-1583039949165-96ee24b0d8de', 'photo-1568251188392-ae32f898cb3b', 'photo-1746025241709-0fb2528687e2', 'photo-1568252542512-9fe8fe9c87bb'],
        'فساتين كاجوال' => ['photo-1617258856138-402b60da4e2a', 'photo-1610048616025-11a3dcc9fd0b', 'photo-1682183948920-16d882bd786d', 'photo-1539008835657-9e8e9680c956'],
        'بلوزات وقمصان' => ['photo-1789110521592-044c58391f7f', 'photo-1761117228880-df2425bd70da', 'photo-1611235116156-0cbda6649efb', 'photo-1764337593519-c51a77b4fc3d', 'photo-1613891737415-be7670d21c19'],
        'بناطيل وجيبات' => ['photo-1552874869-5c39ec9288dc', 'photo-1680690517143-d813ac08cd13', 'photo-1789110520253-7e31dbdd2aad'],
        'عبايات وقفاطين' => ['photo-1662806407800-56793fa8e924', 'photo-1708151729245-fa18bd75727e', 'photo-1613611927458-3ddd4b0afdb9'],
        'ملابس رجالي' => ['photo-1624835567150-0c530a20d8cc', 'photo-1642764873654-9eef0467b342', 'photo-1621072156002-e2fccdc0b176'],
        'قمصان' => ['photo-1602810318383-e386cc2a3ccf', 'photo-1740711152088-88a009e877bb', 'photo-1602810316693-3667c854239a', 'photo-1603252110481-7ba873bf42ab', 'photo-1626557981101-aae6f84aa6ff'],
        'تيشيرتات وبولو' => ['photo-1562157873-818bc0726f68', 'photo-1529374255404-311a2a4f1fd9', 'photo-1635274605638-d44babc08a4f', 'photo-1716951219353-e4a16dce3186'],
        'بناطيل وجينز' => ['photo-1602293589930-45aad59ba3ab', 'photo-1714729382668-7bc3bb261662', 'photo-1714143136372-ddaf8b606da7', 'photo-1637069585336-827b298fe84a'],
        'جاكيتات وبلوفرات' => ['photo-1591047139829-d91aecb6caea', 'photo-1641642231157-0849081598a2', 'photo-1646270968802-6bad28659329', 'photo-1631541909061-71e349d1f203'],
        'ملابس أطفال' => ['photo-1611708314849-8bb91fe0fa56', 'photo-1607453998825-f3f36da5ab18'],
        'ملابس أولاد' => ['photo-1758782213532-bbb5fd89885e'],
        'ملابس بنات' => ['photo-1518831959646-742c3a14ebf7', 'photo-1611708314849-8bb91fe0fa56', 'photo-1607453998825-f3f36da5ab18'],
        'أحذية' => ['photo-1560769629-975ec94e6a86', 'photo-1608231387042-66d1773070a5', 'photo-1595950653106-6c9ebd614d3a'],
        'أحذية حريمي' => ['photo-1543163521-1bf539c55dd2', 'photo-1581101767113-1677fc2beaa8', 'photo-1590099033615-be195f8d575c', 'photo-1562687848-c1664eff566d'],
        'كعب عالي' => ['photo-1535043934128-cf0b28d52f95', 'photo-1518049362265-d5b2a6467637', 'photo-1562687848-c1664eff566d', 'photo-1581101767113-1677fc2beaa8'],
        'باليرينا وفلات' => ['photo-1758542988664-49951c5b1999', 'photo-1769981654964-e65981aecd75', 'photo-1712512343634-f64161d3daa6', 'photo-1775297832774-9eb647970c68'],
        'أحذية رجالي' => ['photo-1614252235316-8c857d38b5f4', 'photo-1603191659812-ee978eeeef76', 'photo-1549290127-7f758fdadff7', 'photo-1563434564528-8fdf5996e622'],
        'أحذية كلاسيك' => ['photo-1571859856639-d54ab2c18ba0', 'photo-1625357165350-bdbcb6d7d524', 'photo-1563433337461-5b65f0834f3a', 'photo-1614253429340-98120bd6d753'],
        'سنيكرز' => ['photo-1595950653106-6c9ebd614d3a', 'photo-1608231387042-66d1773070a5', 'photo-1560769629-975ec94e6a86'],
        'حجاب وطرح' => ['photo-1585728748176-455ac5eed962', 'photo-1614177919002-8b868f0ac5bb', 'photo-1613447895817-e617a4093f50'],
        'شنط وإكسسوارات' => ['photo-1598532163257-ae3c6b2524b6', 'photo-1590874103328-eac38a683ce7', 'photo-1605733513597-a8f8341084e6', 'photo-1559563458-527698bf5295'],
        'شنط يد' => ['photo-1584917865442-de89df76afd3', 'photo-1600857062241-98e5dba7f214', 'photo-1566150905458-1bf1fc113f0d', 'photo-1705909237050-7a7625b47fac'],
        'أحزمة وكوفيات' => ['photo-1711443982852-b3df5c563448', 'photo-1624222247344-550fb60583dc', 'photo-1705493655920-20c572928501', 'photo-1664286074176-5206ee5dc878'],
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
        ['سواريه طويل', 'فستان سواريه مطرز بالترتر', 1850, 2400, 'apparel', true],
        ['فساتين كاجوال', 'فستان كاجوال قطن مشجر', 650, 820, 'apparel', false],
        ['سواريه قصير', 'فستان سهرة مخمل طويل', 2200, null, 'apparel', true],
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

        ['كعب عالي', 'حذاء كعب عالي جلد', 1150, 1450, 'shoes', true],
        ['باليرينا وفلات', 'باليرينا جلد طبيعي', 680, null, 'shoes', false],
        ['أحذية كلاسيك', 'حذاء كلاسيك جلد طبيعي', 1480, 1850, 'shoes', true],
        ['سنيكرز', 'سنيكرز رياضي مريح', 920, null, 'shoes', false],

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

        $seed = function (array $nodes, ?Category $parent) use (&$seed, &$map, &$order): void {
            foreach ($nodes as $name => $children) {
                $category = Category::updateOrCreate(
                    ['slug' => 'cat-'.++$order],
                    ['name' => $name, 'is_active' => true],
                );

                // parent_id is a nested-set move: assigning it appends the node
                // under its parent (or makes it a root) and fixes _lft/_rgt.
                $category->parent_id = $parent?->getKey();
                $category->save();

                $this->seedCategoryImage($category, $order);

                $map[$name] = $category;

                $seed($children, $category);
            }
        };

        $seed(self::CATEGORIES, null);

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
     * Two real photographs per product, drawn from its category's photo pool
     * so the shot matches what is being sold. Falls back to a generated
     * swatch when the pool is empty or the download fails.
     */
    private function seedImages(Product $product, int $index): void
    {
        $disk = Storage::disk('public');
        $pool = self::CATEGORY_PHOTOS[$product->category->name] ?? [];

        foreach (range(0, 1) as $n) {
            $path = "products/demo-{$product->id}-{$n}.jpg";

            if ($disk->exists($path)) {
                continue;
            }

            // Skip index 0 — that one is the category's own image — and walk
            // the pool so neighbouring products don't repeat a shot.
            $photo = $pool === []
                ? null
                : $pool[(($index * 2) + $n) % max(count($pool) - 1, 1) + 1] ?? $pool[0];

            $binary = ($photo ? $this->fetchUnsplashPhoto($photo, 900, 1200) : null)
                ?? $this->gradientJpeg($index + $n, 900, 1200);

            $disk->put($path, $binary);
        }

        foreach (range(0, 1) as $n) {
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'path' => "products/demo-{$product->id}-{$n}.jpg"],
                [
                    'alt' => $product->name,
                    'sort_order' => $n,
                    'is_primary' => $n === 0,
                ],
            );
        }
    }

    /**
     * Give each category a real photograph to show in the mega menu.
     *
     * Cropped to a uniform 800x600 so the menu tiles line up. Already
     * downloaded files are reused, and a failed download falls back to a
     * generated swatch so seeding still completes offline.
     */
    private function seedCategoryImage(Category $category, int $index): void
    {
        $disk = Storage::disk('public');
        $path = "categories/{$category->slug}.jpg";

        if (! $disk->exists($path)) {
            $photo = self::CATEGORY_PHOTOS[$category->name][0] ?? null;

            $binary = ($photo ? $this->fetchUnsplashPhoto($photo, 800, 600) : null)
                ?? $this->gradientJpeg($index, 800, 600);

            $disk->put($path, $binary);
        }

        if ($category->image !== $path) {
            // Quietly: `image` is not a tree move, and saveQuietly keeps the
            // nested-set hooks out of a plain column update.
            $category->forceFill(['image' => $path])->saveQuietly();
        }
    }

    /** One Unsplash photo, centre-cropped to $width x $height, or null. */
    private function fetchUnsplashPhoto(string $photo, int $width, int $height): ?string
    {
        try {
            // Straight to the image CDN: the unsplash.com/photos/{id}/download
            // redirect lands here anyway, and skipping it saves a round trip
            // and a second hostname to resolve.
            $response = Http::withUserAgent('onlineStore-seeder/1.0')
                ->timeout(30)
                ->retry(2, 750)
                ->get("https://images.unsplash.com/{$photo}", [
                    'w' => $width,
                    'h' => $height,
                    'fit' => 'crop',
                    'fm' => 'jpg',
                    'q' => 85,
                ]);

            return $response->successful()
                ? $this->cropToFill($response->body(), $width, $height)
                : null;
        } catch (\Throwable) {
            // Offline or rate limited: the caller falls back to a swatch
            // rather than failing the whole seed.
            return null;
        }
    }

    /** Centre-crop and resize to exactly $width x $height. */
    private function cropToFill(string $binary, int $width, int $height): ?string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        // Scale so the shorter side fills, then take the middle of the longer.
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);

        $canvas = imagecreatetruecolor($width, $height);

        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            (int) round(($sourceWidth - $cropWidth) / 2),
            (int) round(($sourceHeight - $cropHeight) / 2),
            $width, $height,
            $cropWidth, $cropHeight,
        );

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        return $jpeg;
    }

    /** Soft vertical gradient — the offline stand-in for a real photo. */
    private function gradientJpeg(int $index, int $width, int $height): string
    {
        $palette = [
            [206, 161, 123], [120, 134, 158], [168, 120, 128], [112, 142, 128],
            [148, 136, 170], [190, 168, 120], [130, 130, 142], [172, 142, 122],
        ];

        [$r, $g, $b] = $palette[$index % count($palette)];

        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $shade = 1 - ($y / $height) * 0.35;
            $line = imagecolorallocate(
                $image,
                (int) ($r * $shade),
                (int) ($g * $shade),
                (int) ($b * $shade),
            );
            imageline($image, 0, $y, $width, $y, $line);
        }

        ob_start();
        imagejpeg($image, null, 82);
        $jpeg = ob_get_clean();

        imagedestroy($image);

        return $jpeg;
    }
}
