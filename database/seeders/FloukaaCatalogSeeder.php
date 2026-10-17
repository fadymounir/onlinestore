<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * The floukaa.com catalogue — an Egyptian stainless-steel accessories shop.
 *
 * Categories, products, prices, descriptions, variation options and photo URLs
 * were scraped from the shop's WooCommerce Store API (`wc/store/v1/products`)
 * plus `wp/v2/product` for publish dates, and normalised into
 * `database/data/floukaa-catalog.json`. That file is the input here; nothing is
 * fetched from their API at seed time, so seeding is reproducible. Only the
 * photographs are pulled over the network, and only once — already-downloaded
 * files on the public disk are reused.
 *
 * What is NOT theirs, because their API does not expose it, is generated and
 * flagged `FAKE` at each site below: brands, per-variant stock, cost prices and
 * the featured flag.
 *
 * This seeder owns the whole catalogue: it clears categories, products and
 * everything hanging off them before inserting, so re-running it replaces the
 * catalogue rather than growing it.
 */
class FloukaaCatalogSeeder extends Seeder
{
    private const DATA_FILE = 'data/floukaa-catalog.json';

    /** Photos are fetched in pools this wide — polite, but not single-file slow. */
    private const CONCURRENCY = 12;

    private const USER_AGENT = 'simple-seeder/1.0 (+catalogue import)';

    /**
     * Everything the catalogue owns, child-before-parent.
     *
     * Orders are in the list because `order_items` points at variants: the rows
     * SalesSeeder builds are about products that are being replaced, so they
     * are cleared too and SalesSeeder — which runs after — rebuilds them.
     */
    private const OWNED_TABLES = [
        'wishlists',
        'reviews',
        'cart_items',
        'carts',
        'coupon_redemptions',
        'order_status_histories',
        'order_items',
        'orders',
        'attribute_value_product_variant',
        'product_variants',
        'attribute_product',
        'product_images',
        'products',
        'categories',
        'brands',
        'attribute_values',
        'attributes',
    ];

    public function run(): void
    {
        $data = $this->readDataFile();

        $this->wipeCatalogue();

        $attributes = $this->seedAttributes($data['attributes']);
        $brands = $this->seedBrands($data['brands']);
        $categories = $this->seedCategories($data['categories']);

        $this->seedProducts($data['products'], $categories, $brands, $attributes);
        $this->seedCategoryImages($data['categories']);
    }

    /** @return array{attributes: array, brands: array, categories: array, products: array} */
    private function readDataFile(): array
    {
        $path = database_path(self::DATA_FILE);

        if (! is_file($path)) {
            throw new \RuntimeException("Catalogue data file missing: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach (['attributes', 'brands', 'categories', 'products'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                throw new \RuntimeException("Catalogue data file is missing `{$key}`.");
            }
        }

        return $data;
    }

    /**
     * Drop the previous catalogue.
     *
     * Foreign keys go off for the duration rather than being deleted around:
     * `products.category_id` is restrictOnDelete, so categories could not be
     * truncated while any product still pointed at them.
     */
    private function wipeCatalogue(): void
    {
        $this->command?->info('Clearing the existing catalogue…');

        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::OWNED_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Variation attributes — "مقاس الخاتم", "طول السلسلة", "اللون" — with the
     * exact option lists the shop offers.
     *
     * @param  array<int, array>  $definitions
     * @return array<string, array{id: int, values: array<string, int>}>
     */
    private function seedAttributes(array $definitions): array
    {
        $map = [];

        foreach ($definitions as $i => $definition) {
            $attributeId = DB::table('attributes')->insertGetId([
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'type' => ($definition['type'] === 'color' ? AttributeType::Color : AttributeType::Select)->value,
                'sort_order' => $i,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = [];

            foreach ($definition['values'] as $j => $value) {
                $rows[] = [
                    'attribute_id' => $attributeId,
                    'value' => $value['value'],
                    'color_hex' => $value['color_hex'],
                    'sort_order' => $j,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('attribute_values')->insert($rows);

            $map[$definition['slug']] = [
                'id' => $attributeId,
                'values' => DB::table('attribute_values')
                    ->where('attribute_id', $attributeId)
                    ->pluck('id', 'value')
                    ->all(),
            ];
        }

        $this->command?->info('Attributes: '.count($map));

        return $map;
    }

    /**
     * FAKE — the shop exposes no brand on any product, but the storefront has a
     * brand filter and the product card shows one, so the catalogue gets a
     * plausible set and each product is assigned one deterministically.
     *
     * @param  array<int, array{slug: string, name: string}>  $brands
     * @return array<string, int>
     */
    private function seedBrands(array $brands): array
    {
        DB::table('brands')->insert(array_map(fn (array $brand) => [
            'name' => $brand['name'],
            'slug' => $brand['slug'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $brands));

        return DB::table('brands')->pluck('id', 'slug')->all();
    }

    /**
     * The shop's own taxonomy, rebuilt as a nested set.
     *
     * Parents are guaranteed to come before their children in the data file, so
     * one pass is enough. `parent_id` is assigned through the model — on a
     * nested set that is a move, which is what maintains `_lft`/`_rgt`.
     *
     * @param  array<int, array>  $categories
     * @return array<string, int>  slug => id
     */
    private function seedCategories(array $categories): array
    {
        $ids = [];

        foreach ($categories as $category) {
            $node = Category::create([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'is_active' => true,
            ]);

            if ($category['parent'] !== null) {
                $node->parent_id = $ids[$category['parent']];
                $node->save();
            }

            $ids[$category['slug']] = $node->id;
        }

        $this->command?->info('Categories: '.count($ids));

        return $ids;
    }

    /**
     * Products, their variants and their photographs.
     *
     * Written with the query builder in chunks rather than one Eloquent save
     * per row: there are ~3,700 products, ~4,500 variants and ~4,200 images
     * here, and model events buy nothing on a cold insert.
     *
     * @param  array<int, array>  $products
     * @param  array<string, int>  $categories
     * @param  array<string, int>  $brands
     * @param  array<string, array{id: int, values: array<string, int>}>  $attributes
     */
    private function seedProducts(array $products, array $categories, array $brands, array $attributes): void
    {
        $now = now();
        $rows = [];

        foreach ($products as $product) {
            $rows[] = [
                'category_id' => $categories[$product['category']],
                'brand_id' => $brands[$product['brand']] ?? null,
                'name' => $product['name'],
                'slug' => $product['slug'],
                'short_description' => $product['short_description'],
                'description' => $this->describe($product),
                'base_price' => $product['price'],
                'compare_at_price' => $product['compare_at_price'],
                'is_active' => true,
                // FAKE — the shop has no "featured" flag; ~1 in 50 is picked so
                // the home page has something to show.
                'is_featured' => $product['is_featured'],
                'published_at' => $product['published_at'],
                'created_at' => $product['published_at'] ?? $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        $productIds = DB::table('products')->pluck('id', 'slug')->all();
        $this->command?->info('Products: '.count($productIds));

        $this->seedVariants($products, $productIds, $attributes, $now);
        $this->seedProductImages($products, $productIds, $now);
    }

    /**
     * Append the shop's own spec rows (اللون، الخامة) to the description.
     *
     * These come from WooCommerce attributes that do not drive variations, so
     * they are facts about the product rather than a choice the customer makes
     * — they belong in the copy, not in a picker with one option.
     */
    private function describe(array $product): ?string
    {
        $description = $product['description'];

        if ($product['specs'] === []) {
            return $description;
        }

        $items = '';

        foreach ($product['specs'] as $label => $value) {
            $items .= '<li>'.e($label).': '.e($value).'</li>';
        }

        return trim(($description ?? '').'<ul>'.$items.'</ul>');
    }

    /**
     * One variant per combination the shop sells.
     *
     * Products with no variation attributes still get a single variant: it is
     * the purchasable row the cart adds, and the storefront shows no picker for
     * a product with no attributes attached.
     *
     * @param  array<int, array>  $products
     * @param  array<string, int>  $productIds
     * @param  array<string, array{id: int, values: array<string, int>}>  $attributes
     */
    private function seedVariants(array $products, array $productIds, array $attributes, \DateTimeInterface $now): void
    {
        $variantRows = [];
        $pickerRows = [];

        foreach ($products as $product) {
            $productId = $productIds[$product['slug']];

            foreach ($product['pickers'] as $i => $slug) {
                $pickerRows[] = [
                    'product_id' => $productId,
                    'attribute_id' => $attributes[$slug]['id'],
                    'sort_order' => $i,
                ];
            }

            foreach ($product['variants'] as $n => $variant) {
                $variantRows[] = [
                    'product_id' => $productId,
                    'sku' => $variant['sku'],
                    'price' => $product['price'],
                    'compare_at_price' => $product['compare_at_price'],
                    // FAKE — no cost price is public; 55% of retail is a
                    // plausible margin for costume jewellery.
                    'cost_price' => round($product['price'] * 0.55, 2),
                    // FAKE — the Store API reports in/out of stock, never a
                    // quantity. Deterministic, so re-seeding gives the same numbers.
                    'stock_quantity' => $variant['stock'],
                    'low_stock_threshold' => 5,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($variantRows, 500) as $chunk) {
            DB::table('product_variants')->insert($chunk);
        }

        foreach (array_chunk($pickerRows, 500) as $chunk) {
            DB::table('attribute_product')->insert($chunk);
        }

        // The variant rows carry no id until they are back from the database,
        // and the pivot needs them, so the combinations are attached in a
        // second pass keyed by the SKU that was just written.
        $variantIds = DB::table('product_variants')->pluck('id', 'sku')->all();
        $pivotRows = [];

        foreach ($products as $product) {
            foreach ($product['variants'] as $variant) {
                foreach ($variant['values'] as $value) {
                    $pivotRows[] = [
                        'product_variant_id' => $variantIds[$variant['sku']],
                        'attribute_value_id' => $attributes[$value['attribute']]['values'][$value['value']],
                    ];
                }
            }
        }

        foreach (array_chunk($pivotRows, 1000) as $chunk) {
            DB::table('attribute_value_product_variant')->insert($chunk);
        }

        $this->command?->info('Variants: '.count($variantRows).' ('.count($pivotRows).' option links)');
    }

    /**
     * Download every product photograph, then record the rows that point at them.
     *
     * @param  array<int, array>  $products
     * @param  array<string, int>  $productIds
     */
    private function seedProductImages(array $products, array $productIds, \DateTimeInterface $now): void
    {
        $jobs = [];
        $rows = [];

        foreach ($products as $product) {
            foreach ($product['images'] as $n => $image) {
                $path = "products/{$product['slug']}-{$n}.jpg";
                $jobs[$path] = $image['src'];

                $rows[] = [
                    'product_id' => $productIds[$product['slug']],
                    'path' => $path,
                    'alt' => $image['alt'],
                    'sort_order' => $n,
                    'is_primary' => $n === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->fetchImages($jobs, 'product photos');

        // Only record photos that actually landed. A row pointing at a missing
        // file renders as a broken image, whereas a product with no image rows
        // gets the storefront's "بلا صورة" placeholder — which is what a failed
        // download should look like.
        $disk = Storage::disk('public');
        $rows = array_values(array_filter($rows, fn (array $row) => $disk->exists($row['path'])));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_images')->insert($chunk);
        }

        $this->command?->info('Product images: '.count($rows));
    }

    /**
     * Give each category the first photo of a product inside it — which is what
     * the shop shows on its own category tiles.
     *
     * Cropped to a uniform 800x600 so the mega-menu tiles line up, unlike the
     * portrait product shots. A category with nothing in it (and nothing in its
     * descendants) has no photo to borrow and keeps its lettered placeholder.
     *
     * @param  array<int, array>  $categories
     */
    private function seedCategoryImages(array $categories): void
    {
        $disk = Storage::disk('public');
        $withImage = array_values(array_filter($categories, fn (array $c) => $c['image_src'] !== null));

        $this->command?->info('Category images: '.count($withImage).' of '.count($categories));

        foreach ($withImage as $category) {
            $path = "categories/{$category['slug']}.jpg";

            if (! $disk->exists($path)) {
                $binary = $this->fetch($category['image_src']);
                $cropped = $binary ? $this->cropToFill($binary, 800, 600) : null;

                if ($cropped === null) {
                    continue;
                }

                $disk->put($path, $cropped);
            }

            // Quietly: `image` is not a tree move, and saveQuietly keeps the
            // nested-set hooks out of a plain column update.
            Category::where('slug', $category['slug'])->first()
                ?->forceFill(['image' => $path])->saveQuietly();
        }
    }

    /**
     * Fetch many images concurrently onto the public disk.
     *
     * Files already present are skipped, so an interrupted seed resumes instead
     * of starting over, and a failed download is reported rather than fatal —
     * the product simply shows the "بلا صورة" placeholder.
     *
     * @param  array<string, string>  $jobs  disk path => source URL
     */
    private function fetchImages(array $jobs, string $label): void
    {
        $disk = Storage::disk('public');
        $pending = array_filter($jobs, fn (string $url, string $path) => ! $disk->exists($path), ARRAY_FILTER_USE_BOTH);

        $total = count($pending);

        if ($total === 0) {
            $this->command?->info("All {$label} already on disk (".count($jobs).' files).');

            return;
        }

        $this->command?->info("Downloading {$total} {$label} (".(count($jobs) - $total).' already on disk)…');

        $bar = $this->command?->getOutput()->createProgressBar($total);
        $bar?->start();
        $failed = 0;

        foreach (array_chunk($pending, self::CONCURRENCY, true) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk): array {
                $requests = [];

                foreach ($chunk as $path => $url) {
                    $requests[] = $pool->as($path)
                        ->withUserAgent(self::USER_AGENT)
                        ->timeout(30)
                        ->get($url);
                }

                return $requests;
            });

            foreach ($chunk as $path => $url) {
                $response = $responses[$path] ?? null;

                // A pool entry is an exception, not a Response, when the
                // connection itself failed.
                if ($response instanceof Response && $response->successful() && $response->body() !== '') {
                    $disk->put($path, $response->body());
                } else {
                    $failed++;
                }

                $bar?->advance();
            }
        }

        $bar?->finish();
        $this->command?->newLine();

        if ($failed > 0) {
            $this->command?->warn("{$failed} {$label} could not be downloaded; those products fall back to a placeholder.");
        }
    }

    /** One image, or null when it could not be fetched. */
    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)->timeout(30)->retry(2, 500)->get($url);

            return $response->successful() && $response->body() !== '' ? $response->body() : null;
        } catch (\Throwable) {
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

        return $jpeg ?: null;
    }
}
