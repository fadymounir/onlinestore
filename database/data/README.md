# Catalogue data

`floukaa-catalog.json` is the storefront's demo catalogue: the real product
range of [floukaa.com](https://floukaa.com), an Egyptian stainless-steel
accessories shop (سلاسل، أساور، خواتم، حلقان، بيرسينج …).

| | |
|---|---|
| Categories | 71, nested two levels |
| Products | 528 — at most 10 per category |
| Variants | 695 |
| Photographs | 528, one per product (URLs only — the seeder downloads them) |
| Prices | EGP 30 – 3,000, as published |

### Sampling knobs

The shop carries ~3,700 products with up to four photos each, which is far more
than a demo store needs. Three environment variables control how much is kept;
the committed file is the default of all three.

| Variable | Default | Effect |
|---|---|---|
| `PER_CATEGORY` | `10` | Most products taken from any one category. `0` = no cap. |
| `IMAGES_PER_PRODUCT` | `1` | Photos per product. `0` = every photo the shop publishes. |
| `FEATURED_COUNT` | `12` | How many products the home page showcases. |

`PER_CATEGORY` is a ceiling, not a quota: at 10, forty-five categories hit it,
twenty-one keep everything they have (some hold a single item), and the four the
shop leaves empty stay empty. Which products survive is a stable hash of the
source id — not "the newest" — so a category keeps its real spread of prices,
dates and variant shapes.

Featured products are chosen *after* capping, so the home page stays full at any
cap; picking first would leave it nearly empty at `PER_CATEGORY=10`.

```sh
python3 database/data/scrape-floukaa.py                        # 528 products
PER_CATEGORY=50 python3 database/data/scrape-floukaa.py        # 1,485
PER_CATEGORY=0 IMAGES_PER_PRODUCT=0 \
  python3 database/data/scrape-floukaa.py                      # the whole shop
```

## Where it comes from

The shop runs WooCommerce with its REST endpoints open, so
`scrape-floukaa.py` reads the catalogue from the API instead of parsing HTML:

| Endpoint | Used for |
|---|---|
| `wc/store/v1/products` | names, slugs, SKUs, prices, descriptions, categories, variation options, image URLs |
| `wp/v2/product_cat` | the category tree |
| `wp/v2/product` | publish dates, which the Store API omits |

```sh
python3 database/data/scrape-floukaa.py            # rewrite the data file
python3 database/data/scrape-floukaa.py /tmp/new.json   # or diff it first
```

Stdlib only. Re-running is safe: every generated value is derived from the
source product id, so the same catalogue produces the same file.

## What is real and what is invented

Scraped verbatim: category tree, product names, slugs, SKUs, prices, sale
prices, descriptions, variation options (مقاس الخاتم، طول السلسلة، اللون …),
publish dates, photographs.

Generated, because the shop's API exposes none of it:

| Field | How |
|---|---|
| Brands | 8 invented names, assigned per product by a hash of its source id |
| `stock_quantity` | drawn from a fixed pool per variant (some deliberately 0) |
| `cost_price` | 55% of retail |
| `is_featured` | ~1 product in 50, so the home page has something to show |

Two editorial changes keep the catalogue coherent with an Arabic-only
storefront: English category labels (`BAG`, `WATCH`, `tokka`) carry an Arabic
display name, and percent-encoded Arabic slugs are transliterated so storefront
URLs stay readable. Both are recorded in `AR_NAME` / `SLUG_FIX` in the scraper,
next to the original names. Product names are left exactly as the shop writes
them, which is mostly English.

Dropped: the `UP TO 50%` promotional category (a marketing bucket, not a
taxonomy — the discount lives in `compare_at_price`), `Uncategorized`, and the
11 products that sat in neither, which are the shop's own test entries.

## Loading it

`Database\Seeders\FloukaaCatalogSeeder` reads this file. It owns the whole
catalogue — it clears categories, products, variants, images and the orders that
reference them before inserting, so re-running replaces rather than appends.
It is the only thing that touches the network, fetching each photograph once
onto the `public` disk and skipping files already there.
