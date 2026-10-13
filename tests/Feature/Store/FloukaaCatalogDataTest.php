<?php

namespace Tests\Feature\Store;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Integrity of `database/data/floukaa-catalog.json`.
 *
 * The file is 3.5MB of scraped catalogue that FloukaaCatalogSeeder inserts with
 * the query builder, in bulk — so a bad row surfaces as a constraint violation
 * halfway through a seed that has already downloaded thousands of photographs.
 * These checks assert the things the schema would otherwise enforce the hard
 * way, and run without touching the database or the network.
 */
class FloukaaCatalogDataTest extends TestCase
{
    /** @return array{attributes: array, brands: array, categories: array, products: array} */
    private function catalogue(): array
    {
        static $data;

        return $data ??= json_decode(
            (string) file_get_contents(database_path('data/floukaa-catalog.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    public function test_the_catalogue_is_a_real_scrape(): void
    {
        $catalogue = $this->catalogue();

        // Guards against a half-finished or empty scrape being committed. How
        // many products survive depends on PER_CATEGORY in the scraper, so only
        // a floor is asserted — but the taxonomy is always the shop's full set.
        $this->assertGreaterThan(100, count($catalogue['products']));
        $this->assertGreaterThan(50, count($catalogue['categories']));
        $this->assertNotEmpty($catalogue['attributes']);
        $this->assertNotEmpty($catalogue['brands']);
    }

    /**
     * Sampling must not empty a branch of the tree.
     *
     * A category left with nothing is a dead end in the mega menu, so whatever
     * the cap, almost all of the shop's stocked categories must keep something.
     * (Four are empty on the shop itself and stay that way.)
     */
    public function test_the_sample_keeps_stock_in_nearly_every_category(): void
    {
        $perCategory = array_count_values(array_column($this->catalogue()['products'], 'category'));

        $this->assertGreaterThanOrEqual(60, count($perCategory));
        $this->assertGreaterThanOrEqual(1, min($perCategory));
    }

    /**
     * The home page showcases eight featured products, so the catalogue has to
     * carry at least that many however small the per-category cap is — which is
     * why the scraper picks them after capping rather than before.
     */
    public function test_enough_products_are_featured_to_fill_the_home_page(): void
    {
        $featured = array_filter($this->catalogue()['products'], fn (array $p) => $p['is_featured']);

        $this->assertGreaterThanOrEqual(8, count($featured));
    }

    #[DataProvider('uniqueKeys')]
    public function test_keys_the_schema_requires_to_be_unique_are_unique(string $collection, string $key): void
    {
        $values = array_column($this->catalogue()[$collection], $key);

        $duplicates = array_keys(array_filter(array_count_values($values), fn (int $n) => $n > 1));

        $this->assertSame([], $duplicates, "Duplicate {$collection}.{$key}: ".implode(', ', $duplicates));
    }

    /** @return array<string, array{string, string}> */
    public static function uniqueKeys(): array
    {
        return [
            'category slugs' => ['categories', 'slug'],
            'product slugs' => ['products', 'slug'],
            'brand slugs' => ['brands', 'slug'],
            'attribute slugs' => ['attributes', 'slug'],
        ];
    }

    public function test_variant_skus_are_unique_across_the_catalogue(): void
    {
        $skus = [];

        foreach ($this->catalogue()['products'] as $product) {
            foreach ($product['variants'] as $variant) {
                $skus[] = $variant['sku'];
            }
        }

        $duplicates = array_keys(array_filter(array_count_values($skus), fn (int $n) => $n > 1));

        $this->assertSame([], $duplicates, 'Duplicate SKUs: '.implode(', ', $duplicates));
        $this->assertGreaterThan(count($this->catalogue()['products']), count($skus));
    }

    public function test_every_reference_resolves(): void
    {
        $catalogue = $this->catalogue();
        $categories = array_column($catalogue['categories'], 'slug');
        $brands = array_column($catalogue['brands'], 'slug');

        $options = [];

        foreach ($catalogue['attributes'] as $attribute) {
            $options[$attribute['slug']] = array_column($attribute['values'], 'value');
        }

        foreach ($catalogue['categories'] as $category) {
            if ($category['parent'] !== null) {
                $this->assertContains($category['parent'], $categories,
                    "Category {$category['slug']} points at a missing parent.");
            }
        }

        foreach ($catalogue['products'] as $product) {
            $this->assertContains($product['category'], $categories,
                "Product {$product['slug']} points at a missing category.");
            $this->assertContains($product['brand'], $brands,
                "Product {$product['slug']} points at a missing brand.");

            foreach ($product['pickers'] as $picker) {
                $this->assertArrayHasKey($picker, $options,
                    "Product {$product['slug']} varies by the unknown attribute {$picker}.");
            }

            foreach ($product['variants'] as $variant) {
                foreach ($variant['values'] as $value) {
                    $this->assertContains($value['value'], $options[$value['attribute']] ?? [],
                        "Variant {$variant['sku']} uses an option missing from {$value['attribute']}.");
                }
            }
        }
    }

    /**
     * A product must vary by an attribute at most once.
     *
     * Several of the shop's taxonomies collapse onto one app attribute
     * (`pa_لون-color` and `pa_pin-color` are both اللون), so a product can
     * reach the same one twice. Left unmerged that breaks the unique index on
     * `attribute_product` and would mean a variant carrying two colours.
     */
    public function test_no_product_varies_by_the_same_attribute_twice(): void
    {
        foreach ($this->catalogue()['products'] as $product) {
            $this->assertSame(
                array_values(array_unique($product['pickers'])),
                $product['pickers'],
                "Product {$product['slug']} lists an attribute twice.",
            );

            foreach ($product['variants'] as $variant) {
                $used = array_column($variant['values'], 'attribute');

                $this->assertSame(array_unique($used), $used,
                    "Variant {$variant['sku']} pins the same attribute twice.");
            }
        }
    }

    /**
     * Every product needs exactly the variants its pickers imply, and at least
     * one even with no pickers — the variant is the purchasable row the cart
     * adds, so a product without one cannot be bought.
     */
    public function test_every_product_has_variants_matching_its_pickers(): void
    {
        foreach ($this->catalogue()['products'] as $product) {
            $this->assertNotEmpty($product['variants'], "Product {$product['slug']} has no variant.");

            foreach ($product['variants'] as $variant) {
                $this->assertSame(
                    count($product['pickers']),
                    count($variant['values']),
                    "Variant {$variant['sku']} does not pin every attribute its product varies by.",
                );
            }
        }
    }

    public function test_column_limits_are_respected(): void
    {
        foreach ($this->catalogue()['products'] as $product) {
            // products.short_description is string(500).
            $this->assertLessThanOrEqual(500, mb_strlen((string) $product['short_description']),
                "Product {$product['slug']} has an over-long short description.");
            $this->assertLessThanOrEqual(255, mb_strlen($product['name']));
            $this->assertLessThanOrEqual(255, mb_strlen($product['slug']));

            foreach ($product['variants'] as $variant) {
                $this->assertLessThanOrEqual(255, mb_strlen($variant['sku']));
            }
        }

        foreach ($this->catalogue()['categories'] as $category) {
            $this->assertLessThanOrEqual(255, mb_strlen($category['slug']));
        }
    }

    /** Slugs end up in storefront URLs and in image filenames on disk. */
    public function test_slugs_are_url_and_filename_safe(): void
    {
        $catalogue = $this->catalogue();

        foreach ([...$catalogue['categories'], ...$catalogue['products']] as $row) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $row['slug'],
                "Unsafe slug: {$row['slug']}");
        }
    }

    public function test_prices_are_sane(): void
    {
        foreach ($this->catalogue()['products'] as $product) {
            $this->assertGreaterThan(0, $product['price'], "Product {$product['slug']} is free.");

            if ($product['compare_at_price'] !== null) {
                // Otherwise the storefront shows a "خصم" badge on a price rise.
                $this->assertGreaterThan($product['price'], $product['compare_at_price'],
                    "Product {$product['slug']} is \"discounted\" above its price.");
            }
        }
    }

    /** Colour swatches render the hex, so a colour attribute must carry one. */
    public function test_colour_options_carry_a_hex_code(): void
    {
        foreach ($this->catalogue()['attributes'] as $attribute) {
            $this->assertContains($attribute['type'], ['color', 'select']);

            if ($attribute['type'] !== 'color') {
                continue;
            }

            foreach ($attribute['values'] as $value) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', (string) $value['color_hex'],
                    "Colour {$value['value']} has no usable hex code.");
            }
        }
    }
}
