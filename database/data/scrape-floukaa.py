#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Rebuild database/data/floukaa-catalog.json from floukaa.com.

The shop runs WooCommerce with its REST endpoints open, so the catalogue is
read from the API rather than parsed out of HTML:

  wc/store/v1/products   name, slug, sku, prices, categories, attributes,
                         variations, image URLs, descriptions
  wp/v2/product_cat      the category tree (id, parent, name, slug)
  wp/v2/product          publish dates, which the Store API omits

Nothing here is written to the database. The seeder
(Database\\Seeders\\FloukaaCatalogSeeder) reads the JSON this produces, and is
the only thing that downloads the photographs.

Usage:  python3 database/data/scrape-floukaa.py [output.json]
Stdlib only — no pip install needed.
"""
import concurrent.futures as cf
import hashlib
import html
import json
import os
import re
import sys
import unicodedata
import urllib.error
import urllib.request
from collections import Counter

BASE = 'https://floukaa.com/wp-json'
UA = 'onlineStore-catalogue-scraper/1.0 (+one-off import)'
PER_PAGE = 100
WORKERS = 6
# An explicit argument lets a re-scrape be diffed against the committed file
# before replacing it.
OUT_PATH = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
    os.path.dirname(os.path.abspath(__file__)), 'floukaa-catalog.json')


def get(path, tries=3):
    """One GET returning parsed JSON, or None after `tries` failures."""
    url = f'{BASE}/{path}'
    for attempt in range(tries):
        try:
            request = urllib.request.Request(url, headers={'User-Agent': UA})
            with urllib.request.urlopen(request, timeout=60) as response:
                return json.loads(response.read().decode('utf-8'))
        except (urllib.error.URLError, urllib.error.HTTPError, json.JSONDecodeError,
                TimeoutError, OSError) as error:
            if attempt == tries - 1:
                print(f'  ! {path}: {error}', file=sys.stderr)
                return None
    return None


def total_pages(path):
    """Read X-WP-TotalPages so paging does not have to guess."""
    url = f'{BASE}/{path}'
    request = urllib.request.Request(url, headers={'User-Agent': UA}, method='HEAD')
    with urllib.request.urlopen(request, timeout=60) as response:
        return int(response.headers.get('X-WP-TotalPages') or 1)


def fetch_all(path_template, pages):
    """Every page of a collection, fetched a few at a time."""
    rows = []
    with cf.ThreadPoolExecutor(max_workers=WORKERS) as pool:
        for page in pool.map(lambda n: get(path_template.format(page=n)), range(1, pages + 1)):
            if isinstance(page, list):
                rows.extend(page)
    return rows


print('Fetching categories…')
CAT_PATH = f'wp/v2/product_cat?per_page={PER_PAGE}&page={{page}}&orderby=id&order=asc'
CATS = fetch_all(CAT_PATH, total_pages(CAT_PATH.format(page=1)))
print(f'  {len(CATS)} categories')

print('Fetching products…')
PROD_PATH = f'wc/store/v1/products?per_page={PER_PAGE}&page={{page}}&orderby=id&order=asc'
_products = fetch_all(PROD_PATH, total_pages(PROD_PATH.format(page=1)))
RAW = list({p['id']: p for p in _products}.values())
print(f'  {len(RAW)} products')

print('Fetching publish dates…')
DATE_PATH = f'wp/v2/product?per_page={PER_PAGE}&page={{page}}&orderby=id&order=asc&_fields=id,date,modified'
# Keys are strings: the normaliser looks dates up by str(product id), matching
# what a JSON round-trip of this map would give.
DATES = {str(row['id']): row for row in fetch_all(DATE_PATH, total_pages(DATE_PATH.format(page=1)))
         if isinstance(row, dict) and 'id' in row}
print(f'  {len(DATES)} dates')

if not CATS or not RAW:
    sys.exit('Fetch failed — refusing to overwrite the data file with a partial catalogue.')

# Promo / junk taxonomy nodes: "UP TO 50%" is a marketing bucket, not a
# category (the discount lives in compare_at_price instead) and Uncategorized
# only holds the shop's own test products.
SKIP_CATS = {579, 15}

# ---------------------------------------------------------------- slugs -----
TRANS = {
 'ا':'a','أ':'a','إ':'a','آ':'a','ء':'','ب':'b','ت':'t','ث':'th','ج':'g','ح':'h',
 'خ':'kh','د':'d','ذ':'z','ر':'r','ز':'z','س':'s','ش':'sh','ص':'s','ض':'d',
 'ط':'t','ظ':'z','ع':'a','غ':'gh','ف':'f','ق':'q','ك':'k','ل':'l','م':'m',
 'ن':'n','ه':'h','و':'w','ي':'y','ى':'a','ة':'a','ئ':'y','ؤ':'w','ّ':'','َ':'',
 'ُ':'','ِ':'','ْ':'','ً':'','ٌ':'','ٍ':'','ـ':'',
}

# Slugs reach storefront URLs and image filenames on disk, so they are held to
# this shape no matter what the source hands over.
SAFE_SLUG = re.compile(r'[a-z0-9]+(?:-[a-z0-9]+)*')

def slugify(text):
    text = html.unescape(text or '').strip().lower()
    out = []
    for ch in text:
        if ch in TRANS:
            out.append(TRANS[ch])
        elif ch.isascii() and (ch.isalnum()):
            out.append(ch)
        elif ch.isspace() or ch in '-_/\\':
            out.append('-')
        elif unicodedata.category(ch).startswith('P'):
            out.append('-')
    s = re.sub(r'-+', '-', ''.join(out)).strip('-')
    return s or 'item'

# --------------------------------------------------------- category names ---
# The storefront is Arabic-only, so the shop's English/transliterated category
# labels get an Arabic display name. The taxonomy itself is exactly theirs.
AR_NAME = {
 'BAG':'شنط', 'kids bag':'شنط أطفال', 'mini bag':'شنط صغيرة',
 'Beach Hats':'قبعات شاطئ', 'WATCH':'ساعات', 'socks':'شرابات',
 'tokka':'توك شعر', 'scarf':'إيشارب', 'arm cuff':'أساور ذراع',
 'chunky bangle':'غوايش عريضة', 'hand chain':'سلاسل يد',
 'chunky necklace':'سلاسل عريضة', 'drop necklace':'سلاسل دلايات',
 'pinky ring':'خواتم بنصر', 'key chains':'ميداليات مفاتيح',
 'kids bracelet':'أساور أطفال', 'kids earrings':'حلقان أطفال',
 'kids necklace':'سلاسل أطفال', 'KIDS GLASSES':'نظارات أطفال',
 'KIDS WATCH':'ساعات أطفال', 'TOY':'ألعاب', 'Hand made':'هاند ميد',
 'toy':'ألعاب',
}
# Same-named siblings in different branches need telling apart.
RENAME = {210:'سلاسل رجالي', 211:'أساور رجالي', 212:'خواتم رجالي'}
SLUG_FIX = {207:'necklaces', 209:'bracelets', 262:'rings', 255:'earrings',
            208:'anklets', 302:'piercing', 335:'brooches', 293:'pendants',
            309:'kids', 322:'hair-clips', 337:'greeting-cards', 578:'belts',
            271:'prayer-beads', 210:'mens-necklaces', 211:'mens-bracelets',
            212:'mens-rings', 328:'bags', 333:'watches', 334:'socks'}

CAT_BY_ID = {c['id']: c for c in CATS}

def cat_depth(cid):
    d, seen = 0, set()
    while CAT_BY_ID.get(cid) and CAT_BY_ID[cid]['parent'] and cid not in seen:
        seen.add(cid); cid = CAT_BY_ID[cid]['parent']; d += 1
    return d

def cat_chain_ok(cid):
    """False if the node or any ancestor is a skipped promo node."""
    seen = set()
    while cid and cid not in seen:
        if cid in SKIP_CATS:
            return False
        seen.add(cid); cid = (CAT_BY_ID.get(cid) or {}).get('parent')
    return True

categories, used_slugs = [], set()
cat_slug = {}
for c in sorted(CATS, key=lambda x: (cat_depth(x['id']), x['menu_order'], x['id'])):
    if not cat_chain_ok(c['id']):
        continue
    name = RENAME.get(c['id']) or AR_NAME.get(c['name'], c['name'])
    # Their slugs are percent-encoded Arabic ("%d8%b7%d9%88%d9%82"), which
    # would leak into every storefront URL — transliterate the name instead.
    woo = c['slug']
    slug = SLUG_FIX.get(c['id']) or (woo if SAFE_SLUG.fullmatch(woo) else slugify(c['name']))
    if not SAFE_SLUG.fullmatch(slug):
        slug = f"category-{c['id']}"
    base = slug
    n = 2
    while slug in used_slugs:
        slug = f'{base}-{n}'; n += 1
    used_slugs.add(slug)
    cat_slug[c['id']] = slug
    categories.append({
        'slug': slug,
        'name': name,
        'parent': cat_slug.get(c['parent']) if c['parent'] else None,
        'source_name': c['name'],
        'source_id': c['id'],
    })

# ------------------------------------------------------------- html/text ----
KEEP = re.compile(r'</?(?:p|br|strong|b|em|i|ul|ol|li)(?:\s[^>]*)?/?>', re.I)

def clean_html(raw):
    if not raw:
        return None
    s = re.sub(r'<(script|style)[^>]*>.*?</\1>', ' ', raw, flags=re.S | re.I)
    # Drop every tag but the small inline/list set worth keeping.
    s = re.sub(r'<[^>]+>', lambda m: m.group(0) if KEEP.fullmatch(m.group(0)) else ' ', s)
    s = html.unescape(s)
    s = re.sub(r'<b(\s[^>]*)?>', '<strong>', s, flags=re.I)
    s = re.sub(r'</b>', '</strong>', s, flags=re.I)
    s = re.sub(r'<i(\s[^>]*)?>', '<em>', s, flags=re.I)
    s = re.sub(r'</i>', '</em>', s, flags=re.I)
    s = re.sub(r'\s+', ' ', s)
    s = re.sub(r'<p>\s*</p>', '', s)
    return s.strip() or None

def to_text(raw, limit=480):
    s = re.sub(r'<[^>]+>', ' ', raw or '')
    s = re.sub(r'\s+', ' ', html.unescape(s)).strip()
    if len(s) > limit:
        s = s[:limit].rsplit(' ', 1)[0].rstrip('،,.') + '…'
    return s or None

# --------------------------------------------------------------- images -----
IMAGES_PER_PRODUCT = int(os.environ.get('IMAGES_PER_PRODUCT') or 1)
def best_image(img):
    """Largest srcset candidate at or under 700px — full-size files are ~270KB."""
    best, best_w = img.get('src'), 10 ** 6
    for part in (img.get('srcset') or '').split(','):
        bits = part.strip().split()
        if len(bits) == 2 and bits[1].endswith('w'):
            try:
                w = int(bits[1][:-1])
            except ValueError:
                continue
            if 400 <= w <= 700 and (best_w > 700 or w > best_w):
                best, best_w = bits[0], w
    return best

# -------------------------------------------------------- attributes -------
COLOR_HEX = {
 'دهبي - gold':'#d4af37','gold':'#d4af37','metallic gold':'#d4af37',
 'فضي - silver':'#c0c0c0','silver':'#c0c0c0','metallic silver':'#c0c0c0',
 'rose gold':'#b76e79','روز جولد - rose gold':'#b76e79',
 'copper-نحاسي':'#b87333','black':'#000000','اسود':'#000000','ابيض':'#ffffff',
 'off white':'#f5f2e9','اوف وايت':'#f5f2e9','brown':'#6d4c41','بني':'#6d4c41',
 'beige':'#d7ccc8','بيج':'#d7ccc8','burgundy':'#7b1f2b','برجاندي':'#7b1f2b',
 'rose brown':'#a1736b','احمر':'#e53935','اصفر':'#fdd835','بينك':'#ff80ab',
 'رمادي':'#9e9e9e','كحلي':'#1a237e','لبني':'#b3e5fc','ازرق':'#1e88e5',
 'اخضر':'#43a047','برتقالي':'#fb8c00','تركواز':'#1de9b6','زيتي':'#6b8e23',
 'فوشيا':'#e91e8c','هوت فوشيا':'#ff2e93','موف':'#9575cd','بطيخي':'#fc6c85',
 'زبرا':'#3e3e3e','ازرق و احمر':'#5c4b8a','بني و تركواز':'#4a8b8b',
 'جولد و احمر':'#c1663f','mix color':'#8e7cc3','summer color':'#ffcc80',
 'Winter colors':'#78909c','colorful':'#8e7cc3',
}
# Arabic display names for the variation attributes worth exposing as pickers.
ATTR_META = {
 'pa_ring-size':       ('ring-size', 'مقاس الخاتم', 'select'),
 'pa_lمقاس-غوايش':     ('bangle-size', 'مقاس الغويشة', 'select'),
 'pa_مقاس-الاسوره':    ('bracelet-size', 'مقاس الأسورة', 'select'),
 'pa_لون-color':       ('color', 'اللون', 'color'),
 'pa_لون-calarful':    ('color', 'اللون', 'color'),
 'pa_pin-color':       ('color', 'اللون', 'color'),
 'pa_necklace':        ('chain-length', 'طول السلسلة', 'select'),
 'pa_طول-سلسله':       ('chain-length', 'طول السلسلة', 'select'),
 'pa_اسامي-عربي':      ('arabic-name', 'الاسم بالعربي', 'select'),
 'pa_حروف-عربي':       ('arabic-letter', 'الحرف بالعربي', 'select'),
 'pa_english-letter':  ('english-letter', 'الحرف بالإنجليزي', 'select'),
 'pa_اسماء-44':        ('divine-name', 'اسم من أسماء الله الحسنى', 'select'),
 'pa_double-face-size':('stud-size', 'مقاس العمود', 'select'),
 'pa_peircing':        ('stud-size', 'مقاس العمود', 'select'),
}
SPEC_LABEL = {'pa_الخامة-material': 'الخامة', 'pa_لون-color': 'اللون'}

# ---------------------------------------------------------------- brands ----
BRANDS = [
 'فلوكا', 'ستانلس هاوس', 'لمسة ذهب', 'أناقة مصر', 'سيلفر لاين',
 'كايرو جيمز', 'نيل أكسسوارز', 'جولدن تاتش',
]
BRAND_ROWS = [{'slug': f'brand-{i+1}', 'name': n} for i, n in enumerate(BRANDS)]

def stable(seed, *parts):
    """Deterministic pseudo-random int — same data file, same fake numbers."""
    h = hashlib.sha1((str(seed) + '|' + '|'.join(map(str, parts))).encode()).digest()
    return int.from_bytes(h[:4], 'big')

STOCK_POOL = [0, 2, 3, 5, 8, 12, 15, 18, 24, 30, 36, 42, 55, 70]

# -------------------------------------------------------------- products ----
products, attr_values, seen_slugs, seen_sku = [], {}, set(), set()
skipped = Counter()

for p in sorted(RAW, key=lambda x: x['id']):
    cands = [c for c in (p.get('categories') or []) if cat_chain_ok(c['id']) and c['id'] in cat_slug]
    if not cands:
        skipped['no-category'] += 1
        continue
    if re.fullmatch(r'test\d*', (p['name'] or '').strip(), re.I):
        skipped['test-product'] += 1
        continue
    # Most specific wins: deepest in the tree, then the smaller category.
    cands.sort(key=lambda c: (-cat_depth(c['id']), CAT_BY_ID[c['id']]['count']))
    category = cat_slug[cands[0]['id']]

    # Most product slugs are clean English, but ~200 are percent-encoded Arabic
    # or emoji ("%d8%b3%d9%84%d8%b3%d9%84%d9%87-...", "abu-obaida-%f0%9f%94%ba").
    # Those get transliterated from the name instead.
    slug = p['slug'] if SAFE_SLUG.fullmatch(p['slug'] or '') else slugify(p['name'])
    if not SAFE_SLUG.fullmatch(slug):
        slug = f"product-{p['id']}"
    if slug in seen_slugs:
        slug = f"{slug}-{p['id']}"
    seen_slugs.add(slug)

    prices = p.get('prices') or {}
    try:
        price = round(float(prices.get('price') or 0), 2)
        regular = round(float(prices.get('regular_price') or 0), 2)
    except ValueError:
        skipped['bad-price'] += 1
        continue
    if price <= 0:
        skipped['zero-price'] += 1
        continue
    compare = regular if (p.get('on_sale') and regular > price) else None

    # Variation-defining attributes become pickers; the rest become spec rows.
    pickers, specs = [], {}
    for a in (p.get('attributes') or []):
        tax, terms = a.get('taxonomy') or '', a.get('terms') or []
        if not terms:
            continue
        if a.get('has_variations') and tax in ATTR_META:
            aslug, aname, atype = ATTR_META[tax]
            bucket = attr_values.setdefault(aslug, {'name': aname, 'type': atype, 'values': {}})
            vals = []
            for t in terms:
                v = html.unescape(t['name']).strip()
                if not v:
                    continue
                bucket['values'].setdefault(v, COLOR_HEX.get(v) if atype == 'color' else None)
                vals.append(v)
            if vals:
                # Several of their taxonomies collapse onto one app attribute
                # (pa_لون-color and pa_pin-color are both اللون), so a product
                # can reach the same attribute twice. Merge instead of adding a
                # second picker: two pickers on one attribute would mean a
                # variant carrying two colours, and a duplicate pivot row.
                existing = next((pk for pk in pickers if pk['attribute'] == aslug), None)
                if existing:
                    existing['values'] += [v for v in vals if v not in existing['values']]
                else:
                    pickers.append({'attribute': aslug, 'values': vals})
        else:
            label = SPEC_LABEL.get(tax) or html.unescape(a.get('name') or '').strip()
            val = '، '.join(html.unescape(t['name']).strip() for t in terms if t.get('name'))
            if label and val:
                specs[label] = val

    # Variants: the cartesian product of the pickers, or one plain row.
    rows, base_sku = [], (p.get('sku') or '').strip() or f"FLK-{p['id']}"
    combos = [[]]
    for pick in pickers:
        combos = [c + [(pick['attribute'], v)] for c in combos for v in pick['values']]
    for n, combo in enumerate(combos):
        sku = base_sku if not combo else f"{base_sku}-" + '-'.join(slugify(v) or str(i) for i, (_, v) in enumerate(combo))
        if sku in seen_sku:
            sku = f"{sku}-{p['id']}-{n}"
        seen_sku.add(sku)
        rows.append({
            'sku': sku[:180],
            'values': [{'attribute': a, 'value': v} for a, v in combo],
            # Fake, deterministic: the shop's API exposes no per-variant stock.
            'stock': STOCK_POOL[stable(p['id'], 'stock', n) % len(STOCK_POOL)],
        })

    # One photo per product by default. The shop lists up to four, but a card
    # only ever shows the primary, so the rest are downloads a demo does not
    # need. IMAGES_PER_PRODUCT=0 keeps every photo the shop publishes.
    photos = [{'src': best_image(i), 'alt': html.unescape(p['name']).strip()}
              for i in (p.get('images') or []) if best_image(i)]
    if IMAGES_PER_PRODUCT > 0:
        photos = photos[:IMAGES_PER_PRODUCT]

    d = DATES.get(str(p['id'])) or {}
    published = (d.get('date') or '').replace('T', ' ') or None

    products.append({
        'slug': slug,
        'name': html.unescape(p['name']).strip(),
        'category': category,
        'brand': BRAND_ROWS[stable(p['id'], 'brand') % len(BRAND_ROWS)]['slug'],
        'short_description': to_text(p.get('short_description') or p.get('description')),
        'description': clean_html(p.get('description') or p.get('short_description')),
        'specs': specs,
        'price': price,
        'compare_at_price': compare,
        'published_at': published,
        # FAKE — set below, once the per-category cap has decided which
        # products survive.
        'is_featured': False,
        'pickers': [pk['attribute'] for pk in pickers],
        'variants': rows,
        'images': photos,
        'source_id': p['id'],
        'source_url': p.get('permalink'),
    })

# --------------------------------------------------------- per-category cap ---
# The shop carries ~3,700 products, which is more catalogue than a demo store
# needs. Keep at most PER_CATEGORY from each category; a category holding fewer
# keeps all of them — at the default of 10 that is 21 of the 67 stocked
# categories, some with a single item — so this is a ceiling, not a quota.
#
# Which ones survive is a stable hash of the source id rather than "the oldest"
# or "the newest", so each category keeps its real spread of prices, publish
# dates, photo counts and variant shapes. Set PER_CATEGORY=0 to keep everything.
PER_CATEGORY = int(os.environ.get('PER_CATEGORY') or 10)

if PER_CATEGORY > 0:
    kept, per_category = [], Counter()

    for product in sorted(products, key=lambda p: stable(p['source_id'], 'sample')):
        if per_category[product['category']] < PER_CATEGORY:
            per_category[product['category']] += 1
            kept.append(product)

    # Back into source order, so the data file stays readable and diffable.
    products = sorted(kept, key=lambda p: p['source_id'])

    at_cap = sum(1 for n in per_category.values() if n == PER_CATEGORY)
    print(f'capped at {PER_CATEGORY}/category: {len(products)} products across '
          f'{len(per_category)} categories ({at_cap} at the cap)')

# FAKE — the shop publishes no "featured" flag, and the home page showcases
# eight of them. Chosen after the cap so the count holds whatever PER_CATEGORY
# is set to; picking before would leave the shelf nearly empty at a small cap.
FEATURED_COUNT = int(os.environ.get('FEATURED_COUNT') or 12)

for product in sorted(products, key=lambda p: stable(p['source_id'], 'featured'))[:FEATURED_COUNT]:
    product['is_featured'] = True

# A category tile needs a photo: borrow the first image of a product inside it
# (or inside any descendant), which is what the shop shows there anyway.
first_img = {}
for pr in products:
    if pr['images']:
        first_img.setdefault(pr['category'], pr['images'][0]['src'])
children = {}
for c in categories:
    children.setdefault(c['parent'], []).append(c['slug'])

def pick_img(slug, seen=None):
    seen = seen or set()
    if slug in seen:
        return None
    seen.add(slug)
    if slug in first_img:
        return first_img[slug]
    for ch in children.get(slug, []):
        got = pick_img(ch, seen)
        if got:
            return got
    return None

for c in categories:
    c['image_src'] = pick_img(c['slug'])



data = {
    'source': 'https://floukaa.com',
    'source_api': 'wc/store/v1 + wp/v2',
    'currency': 'EGP',
    'attributes': [
        {'slug': s, 'name': b['name'], 'type': b['type'],
         'values': [{'value': v, 'color_hex': h} for v, h in b['values'].items()]}
        for s, b in attr_values.items()
    ],
    'brands': BRAND_ROWS,
    'categories': categories,
    'products': products,
}

with open(OUT_PATH, 'w', encoding='utf-8') as handle:
    json.dump(data, handle, ensure_ascii=False, separators=(',', ':'))

print(f'\nWrote {OUT_PATH}')
print('categories :', len(categories))
print('products   :', len(products))
print('variants   :', sum(len(p['variants']) for p in products))
print('images     :', sum(len(p['images']) for p in products))
print('featured   :', sum(1 for p in products if p['is_featured']))
print('on sale    :', sum(1 for p in products if p['compare_at_price']))
print('cats w/img :', sum(1 for c in categories if c['image_src']), '/', len(categories))
print('skipped    :', dict(skipped))
