<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Fills the database with a production-sized dataset so the storefront and the
 * admin panel can be exercised against real volumes.
 *
 * Everything it writes carries a marker, so the whole set can be taken back out
 * again without touching the floukaa catalogue or the hand-written demo rows:
 *
 *   customers -> email customer{id}@bulk.test
 *   products  -> slug  {template-slug}-bx{id}
 *   variants  -> sku   BX-{id}
 *   orders    -> whatever those customers placed
 *
 * Products are cloned from the real catalogue (names, copy, images, prices) and
 * re-pointed at random leaf categories, so listing pages, filters and the mega
 * menu all see plausible data rather than lorem ipsum.
 */
class SeedBulkData extends Command
{
    protected $signature = 'store:bulk
        {--products=100000 : products to generate}
        {--users=100000 : customers to generate}
        {--orders=100000 : orders to generate}
        {--reviews=100000 : cap on reviews grown from delivered orders}
        {--chunk=1000 : rows per INSERT}
        {--seed=20261027 : RNG seed, so a run is repeatable}
        {--fresh : delete previously generated bulk rows first}
        {--only=* : run only these phases: users, products, orders, reviews, extras}';

    protected $description = 'Generate ~100K products / customers / orders of realistic test data.';

    private const TABLES_USERS = ['users', 'addresses'];

    private const TABLES_PRODUCTS = [
        'products', 'product_images', 'product_variants',
        'attribute_product', 'attribute_value_product_variant',
    ];

    private const TABLES_ORDERS = ['orders', 'order_items', 'order_status_histories', 'coupon_redemptions'];

    /** Variation words appended to a cloned product name. */
    private const TAGS = [
        'ذهبي', 'فضي', 'روز جولد', 'أسود', 'أبيض', 'تركواز', 'لؤلؤي', 'ملكي',
        'كلاسيك', 'مودرن', 'فينتدج', 'ميني', 'ماكسي', 'دبل', 'تويست', 'ستانلس',
        'مطلي ذهب', 'إصدار محدود', 'هدية', 'رمضان', 'العيد', 'الصيف', 'الشتاء',
        'لامع', 'مات', 'مرصع', 'بالزركون', 'بحجر', 'سادة', 'منقوش',
        'gold', 'silver', 'rose gold', 'black edition', 'pearl', 'crystal',
        'classic', 'premium', 'deluxe', 'limited', 'signature', 'mini', 'maxi',
    ];

    private const FIRST_NAMES = [
        'أحمد', 'محمد', 'محمود', 'مصطفى', 'عمر', 'خالد', 'كريم', 'يوسف', 'طارق', 'حسن',
        'إبراهيم', 'عبد الرحمن', 'زياد', 'شريف', 'هاني', 'وليد', 'سامح', 'ياسر', 'مينا', 'بيشوي',
        'منى', 'سارة', 'فاطمة', 'نورهان', 'هدى', 'دينا', 'ياسمين', 'مريم', 'سلمى', 'نهى',
        'إيمان', 'شيماء', 'رنا', 'هبة', 'أميرة', 'جميلة', 'بسمة', 'آية', 'ملك', 'فرح',
    ];

    private const LAST_NAMES = [
        'عبد الرحمن', 'السيد', 'حسن', 'إبراهيم', 'مصطفى', 'عبد العظيم', 'فتحي', 'سمير',
        'عادل', 'طارق', 'خالد', 'منصور', 'شاكر', 'الشربيني', 'البنا', 'عويس', 'زكي',
        'الغندور', 'نصار', 'رمضان', 'شلبي', 'العزب', 'حمدي', 'لطفي', 'سعيد', 'يونس',
    ];

    private const STREETS = [
        'شارع عباس العقاد', 'شارع التحرير', 'شارع الهرم', 'شارع فيصل', 'شارع الجيش',
        'شارع البحر', 'شارع النصر', 'شارع مصطفى النحاس', 'شارع مكرم عبيد', 'شارع الثورة',
        'شارع جامعة الدول العربية', 'شارع شهاب', 'شارع السودان', 'شارع فوزي معاذ',
        'شارع 9 المعادي', 'شارع 306 المعادي الجديدة', 'شارع الميرغني', 'شارع النزهة',
        'شارع بورسعيد', 'شارع الجمهورية', 'شارع صلاح سالم', 'شارع أحمد عرابي',
    ];

    private const LANDMARKS = [
        'أمام سيتي ستارز', 'بجوار محطة المترو', 'فوق صيدلية العزبي', 'أمام مسجد النور',
        'بجوار بنك مصر', 'خلف كارفور', 'أمام المستشفى العام', 'بجوار مدرسة اللغات',
        'فوق محل الأدوات المنزلية', 'ناصية الشارع الرئيسي', null, null,
    ];

    /** Where a generated order ends up, with the note recorded on arrival. */
    private const LIFECYCLES = [
        [42, OrderStatus::Delivered, 'تم التسليم للعميل'],
        [10, OrderStatus::Shipped, 'مع مندوب الشحن'],
        [9, OrderStatus::Processing, 'جاري التجهيز في المخزن'],
        [8, OrderStatus::Confirmed, 'تم التأكيد هاتفياً'],
        [16, OrderStatus::Pending, null],
        [10, OrderStatus::Cancelled, 'العميل غيّر رأيه'],
        [5, OrderStatus::Returned, 'المقاس غير مناسب'],
    ];

    private const REVIEW_COPY = [
        [5, 'خامة ممتازة', 'القطعة وصلت أسرع من المتوقع والخامة أفضل من الصور.'],
        [5, 'يستحق السعر', 'نسبة السعر للجودة ممتازة، والتغليف كان مرتب جداً.'],
        [5, 'تعاملت معاهم كتير', 'دايماً نفس المستوى، وخدمة العملاء بترد بسرعة.'],
        [4, 'جيد جداً', 'المقاس مطابق للجدول والشغل نظيف، بس اللون أغمق شوية.'],
        [4, 'خفيف ومريح', 'استانلس حقيقي، لبسته في البحر ولا اتأكسد.'],
        [4, 'حلو', 'التوصيل كان سريع والقطعة مطابقة للوصف.'],
        [3, 'مقبول', 'القطعة كويسة لكن التوصيل اتأخر يومين عن الموعد.'],
        [2, 'لم يعجبني', 'القفل بيفتح لوحده مع الحركة.'],
        [1, 'غير مطابق', 'وصلني مقاس غير اللي طلبته واضطررت أرجعه.'],
    ];

    private int $chunk = 1000;

    private int $now;

    public function handle(): int
    {
        @ini_set('memory_limit', '1G');
        DB::connection()->disableQueryLog();

        mt_srand((int) $this->option('seed'));
        $this->chunk = max(100, (int) $this->option('chunk'));
        $this->now = time();

        $phases = $this->option('only') ?: ['users', 'products', 'orders', 'reviews', 'extras'];
        $started = microtime(true);

        if ($this->option('fresh')) {
            $this->purge();
        }

        if (in_array('users', $phases, true)) {
            $this->phase('customers', fn () => $this->seedUsers((int) $this->option('users')));
        }

        if (in_array('products', $phases, true)) {
            $this->phase('products', fn () => $this->seedProducts((int) $this->option('products')));
        }

        if (in_array('orders', $phases, true)) {
            $this->phase('orders', fn () => $this->seedOrders((int) $this->option('orders')));
        }

        if (in_array('reviews', $phases, true)) {
            $this->phase('reviews', fn () => $this->seedReviews((int) $this->option('reviews')));
        }

        if (in_array('extras', $phases, true)) {
            $this->phase('carts & wishlists', fn () => $this->seedCartsAndWishlists());
        }

        $this->newLine();
        $this->components->info(sprintf('Finished in %s.', $this->elapsed($started)));
        $this->counts();

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- phases

    private function seedUsers(int $count): void
    {
        if ($count < 1) {
            return;
        }

        $locations = $this->locations();

        if ($locations === []) {
            $this->warn('No governorates/cities — run EgyptLocationSeeder first.');

            return;
        }

        $id = (int) DB::table('users')->max('id') + 1;
        $password = Hash::make('password');     // hashed once; bcrypt is the slow part
        $rows = ['users' => [], 'addresses' => []];
        $bar = $this->bar($count);

        for ($i = 0; $i < $count; $i++, $id++) {
            $name = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)]
                .' '.self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];

            $createdTs = $this->pastTs(1095);
            $created = $this->ts($createdTs);

            $rows['users'][] = [
                'id' => $id,
                'name' => $name,
                'email' => "customer{$id}@bulk.test",
                'phone' => '019'.str_pad((string) $id, 8, '0', STR_PAD_LEFT),
                'email_verified_at' => $this->chance(85) ? $created : null,
                'password' => $password,
                'is_active' => $this->chance(97),
                'last_login_at' => $this->chance(70)
                    ? $this->ts(mt_rand($createdTs, $this->now))
                    : null,
                'remember_token' => null,
                'created_at' => $created,
                'updated_at' => $created,
                'deleted_at' => null,
            ];

            // Home address, plus a work address for roughly one in seven.
            $rows['addresses'][] = $this->addressRow($id, $name, $locations, 'المنزل', true, $created);

            if ($this->chance(15)) {
                $rows['addresses'][] = $this->addressRow($id, $name, $locations, 'العمل', false, $created);
            }

            if (count($rows['users']) >= $this->chunk) {
                $this->write($rows, self::TABLES_USERS);
            }

            $bar->advance();
        }

        $this->write($rows, self::TABLES_USERS);
        $bar->finish();
    }

    private function seedProducts(int $count): void
    {
        if ($count < 1) {
            return;
        }

        $templates = $this->templates();

        if ($templates === []) {
            $this->warn('No catalogue to clone — run FloukaaCatalogSeeder first.');

            return;
        }

        $categories = DB::table('categories')
            ->whereRaw('_rgt = _lft + 1')             // leaves only; products never hang off a branch
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($categories === []) {
            $categories = DB::table('categories')->pluck('id')->all();
        }

        $brands = DB::table('brands')->pluck('id')->all();

        // Attributes a generated product may vary by: ring size, chain length, colour.
        $values = DB::table('attribute_values')
            ->whereIn('attribute_id', [1, 3, 8])
            ->get(['id', 'attribute_id'])
            ->groupBy('attribute_id')
            ->map(fn ($group) => $group->pluck('id')->all())
            ->all();

        $attributeIds = array_keys($values);
        $combos = count($templates) * count(self::TAGS);

        $productId = (int) DB::table('products')->max('id') + 1;
        $variantId = (int) DB::table('product_variants')->max('id') + 1;

        $rows = array_fill_keys(self::TABLES_PRODUCTS, []);
        $bar = $this->bar($count);

        for ($i = 0; $i < $count; $i++, $productId++) {
            $template = $templates[$i % count($templates)];
            $tag = self::TAGS[mt_rand(0, count(self::TAGS) - 1)];
            $round = intdiv($i, $combos);

            $name = trim($template->name.' '.$tag.($round > 0 ? ' '.($round + 1) : ''));
            $createdTs = $this->pastTs(1095);
            $created = $this->ts($createdTs);

            // Variants first: the product's display price is the cheapest of them.
            $variantCount = $this->chance(45) ? 1 : mt_rand(2, 4);
            $attributeId = null;
            $chosenValues = [];

            if ($variantCount > 1 && $attributeIds !== []) {
                $attributeId = $attributeIds[mt_rand(0, count($attributeIds) - 1)];
                $pool = $values[$attributeId];
                shuffle($pool);
                $chosenValues = array_slice($pool, 0, $variantCount);
                $variantCount = count($chosenValues);

                $rows['attribute_product'][] = [
                    'product_id' => $productId,
                    'attribute_id' => $attributeId,
                    'sort_order' => 0,
                ];
            }

            $base = max(35.0, (float) $template->base_price * mt_rand(60, 180) / 100);
            $lowest = null;
            $stockTotal = 0;

            for ($v = 0; $v < $variantCount; $v++, $variantId++) {
                $price = round(($base + $v * mt_rand(0, 40)) * 2, 0) / 2;     // lands on .00/.50
                $lowest = $lowest === null ? $price : min($lowest, $price);

                // One variant in eight is out of stock, one in six is near it.
                $stock = $this->chance(12) ? 0 : mt_rand(1, 240);
                $stockTotal += $stock;

                $rows['product_variants'][] = [
                    'id' => $variantId,
                    'product_id' => $productId,
                    'sku' => 'BX-'.$variantId,
                    'price' => $price,
                    'compare_at_price' => $this->chance(30) ? round($price * 1.25, 2) : null,
                    'cost_price' => round($price * 0.55, 2),
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => $this->chance(60) ? 5 : 0,
                    'image' => null,
                    'is_active' => $this->chance(96),
                    'created_at' => $created,
                    'updated_at' => $created,
                ];

                if ($chosenValues !== []) {
                    $rows['attribute_value_product_variant'][] = [
                        'product_variant_id' => $variantId,
                        'attribute_value_id' => $chosenValues[$v],
                    ];
                }
            }

            // 0.5% sit in the future and 1% in the trash, so the publish
            // schedule and the admin's restore flow both have something to show.
            $publishedTs = $this->chance(1) ? $this->now + mt_rand(86400, 2592000) : $createdTs;

            $rows['products'][] = [
                'id' => $productId,
                'category_id' => $categories[mt_rand(0, count($categories) - 1)],
                'brand_id' => $this->chance(90) ? $brands[mt_rand(0, count($brands) - 1)] : null,
                'name' => $name,
                'slug' => $template->slug.'-bx'.$productId,
                'short_description' => $template->short_description,
                'description' => $template->description,
                'base_price' => $lowest ?? $base,
                // Raw inserts bypass ProductVariantObserver, so the
                // denormalised stock is written here rather than recounted.
                'total_stock' => $stockTotal,
                'compare_at_price' => $this->chance(25) ? round(($lowest ?? $base) * 1.3, 2) : null,
                'is_active' => $this->chance(95),
                'is_featured' => $this->chance(2),
                'published_at' => $this->ts($publishedTs),
                'created_at' => $created,
                'updated_at' => $created,
                'deleted_at' => $this->chance(1) ? $this->ts(mt_rand($createdTs, $this->now)) : null,
            ];

            foreach ($template->images as $sort => $path) {
                $rows['product_images'][] = [
                    'product_id' => $productId,
                    'path' => $path,
                    'alt' => $name,
                    'sort_order' => $sort,
                    'is_primary' => $sort === 0,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }

            if (count($rows['products']) >= $this->chunk) {
                $this->write($rows, self::TABLES_PRODUCTS);
            }

            $bar->advance();
        }

        $this->write($rows, self::TABLES_PRODUCTS);
        $bar->finish();
    }

    private function seedOrders(int $count): void
    {
        if ($count < 1) {
            return;
        }

        $variants = $this->variantPool(60000);

        if ($variants === []) {
            $this->warn('No purchasable variants — seed products first.');

            return;
        }

        $coupons = $this->bulkCoupons();
        $admins = DB::table('admins')->pluck('id')->all();
        $sequences = $this->orderNumberSequences();
        $orderId = (int) DB::table('orders')->max('id') + 1;

        $rows = array_fill_keys(self::TABLES_ORDERS, []);
        $redemptions = [];
        $made = 0;
        $cursor = 0;
        $bar = $this->bar($count);

        while ($made < $count) {
            $customers = $this->customerChunk($cursor, 2000);

            if ($customers === []) {
                if ($cursor === 0) {
                    $this->warn('No customer has a default address — seed customers first.');
                    break;
                }

                $cursor = 0;        // wrap around and give everyone another order

                continue;
            }

            foreach ($customers as $customer) {
                $cursor = (int) $customer->user_id;

                for ($k = 0, $n = $this->ordersPerCustomer(); $k < $n && $made < $count; $k++, $made++) {
                    $discount = $this->buildOrder(
                        $orderId++, $customer, $variants, $coupons, $admins, $sequences, $rows
                    );

                    if ($discount !== null) {
                        $redemptions[$discount] = ($redemptions[$discount] ?? 0) + 1;
                    }

                    $bar->advance();
                }

                if (count($rows['orders']) >= $this->chunk) {
                    $this->write($rows, self::TABLES_ORDERS);
                }

                if ($made >= $count) {
                    break;
                }
            }
        }

        $this->write($rows, self::TABLES_ORDERS);
        $bar->finish();

        foreach ($redemptions as $couponId => $uses) {
            DB::table('coupons')->where('id', $couponId)->increment('used_count', $uses);
        }
    }

    /**
     * Writes one order plus its lines and audit trail.
     *
     * @return int|null the coupon used, so the caller can total up used_count
     */
    private function buildOrder(
        int $id,
        object $customer,
        array $variants,
        array $coupons,
        array $admins,
        array &$sequences,
        array &$rows,
    ): ?int {
        $placedTs = $this->pastTs(540);
        $placed = $this->ts($placedTs);
        [$status, $note] = $this->pickLifecycle();

        $subtotal = 0.0;
        $taken = [];
        $lineCount = mt_rand(1, 4);

        for ($i = 0; $i < $lineCount; $i++) {
            $variant = $variants[mt_rand(0, count($variants) - 1)];

            if (isset($taken[$variant->id])) {
                continue;
            }

            $taken[$variant->id] = true;
            $quantity = $this->chance(70) ? 1 : mt_rand(2, 3);
            $lineTotal = round((float) $variant->price * $quantity, 2);
            $subtotal += $lineTotal;

            $rows['order_items'][] = [
                'order_id' => $id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product_name,
                'variant_label' => $variant->label,
                'sku' => $variant->sku,
                'unit_price' => $variant->price,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'created_at' => $placed,
                'updated_at' => $placed,
            ];
        }

        $coupon = null;
        $discount = 0.0;

        if ($coupons !== [] && $this->chance(18)) {
            $candidate = $coupons[mt_rand(0, count($coupons) - 1)];
            $discount = $this->discountFor($candidate, $subtotal);
            $coupon = $discount > 0 ? $candidate : null;
        }

        $payable = $subtotal - $discount;
        $threshold = $customer->zone_threshold !== null ? (float) $customer->zone_threshold : null;
        $shipping = $threshold !== null && $payable >= $threshold
            ? 0.0
            : (float) ($customer->zone_cost ?? 50);

        $ymd = date('Ymd', $placedTs);
        $sequences[$ymd] = ($sequences[$ymd] ?? 0) + 1;

        $delivered = null;
        $cancelled = null;
        $closedTs = $placedTs + mt_rand(1, 3) * 86400;

        if ($status === OrderStatus::Delivered || $status === OrderStatus::Returned) {
            $closedTs = min($this->now, $placedTs + mt_rand(2, 8) * 86400);
            $delivered = $this->ts($closedTs);
        }

        if ($status === OrderStatus::Cancelled) {
            $closedTs = min($this->now, $placedTs + mt_rand(1, 3) * 86400);
            $cancelled = $this->ts($closedTs);
        }

        $rows['orders'][] = [
            'id' => $id,
            'order_number' => 'ORD-'.$ymd.'-'.str_pad((string) $sequences[$ymd], 4, '0', STR_PAD_LEFT),
            'user_id' => $customer->user_id,
            'status' => $status->value,
            'payment_method' => 'cod',
            'payment_status' => match ($status) {
                OrderStatus::Delivered => PaymentStatus::Paid->value,
                OrderStatus::Returned => PaymentStatus::Refunded->value,
                default => PaymentStatus::Pending->value,
            },
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'shipping_total' => $shipping,
            'grand_total' => round($payable + $shipping, 2),
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'governorate_id' => $customer->governorate_id,
            'recipient_name' => $customer->recipient_name,
            'phone' => $customer->phone,
            'alternate_phone' => $customer->alternate_phone,
            'governorate_name' => $customer->governorate_name,
            'city_name' => $customer->city_name,
            'street' => $customer->street,
            'building' => $customer->building,
            'floor' => $customer->floor,
            'apartment' => $customer->apartment,
            'landmark' => $customer->landmark,
            'customer_note' => $this->chance(20) ? 'يُرجى الاتصال قبل التوصيل بنصف ساعة.' : null,
            'admin_note' => null,
            'cancel_reason' => $status === OrderStatus::Cancelled ? $note : null,
            // Checkout takes the stock at placement; cancelling/returning puts it back.
            'stock_deducted' => ! in_array($status, [OrderStatus::Cancelled, OrderStatus::Returned], true),
            // A returned order was collected for and paid back, so the refund
            // record has to exist alongside the `refunded` payment status.
            'refund_amount' => $status === OrderStatus::Returned ? round($payable + $shipping, 2) : null,
            'refund_reference' => $status === OrderStatus::Returned
                ? 'RFND-'.str_pad((string) $id, 8, '0', STR_PAD_LEFT)
                : null,
            'refund_note' => null,
            'refunded_at' => $status === OrderStatus::Returned ? $this->ts($closedTs) : null,
            'refunded_by' => null,
            'placed_at' => $placed,
            'delivered_at' => $delivered,
            'cancelled_at' => $cancelled,
            'created_at' => $placed,
            'updated_at' => $this->ts($closedTs),
        ];

        $this->appendHistory($id, $status, $note, $admins, $placedTs, $closedTs, $rows);

        if ($coupon !== null) {
            $rows['coupon_redemptions'][] = [
                'coupon_id' => $coupon->id,
                'user_id' => $customer->user_id,
                'order_id' => $id,
                'discount_amount' => round($discount, 2),
                'created_at' => $placed,
                'updated_at' => $placed,
            ];
        }

        return $coupon?->id;
    }

    /** Walks the order through the real intermediate statuses, timestamped in order. */
    private function appendHistory(
        int $orderId,
        OrderStatus $target,
        ?string $note,
        array $admins,
        int $placedTs,
        int $closedTs,
        array &$rows,
    ): void {
        $path = match ($target) {
            OrderStatus::Pending => [],
            OrderStatus::Confirmed => [OrderStatus::Confirmed],
            OrderStatus::Processing => [OrderStatus::Confirmed, OrderStatus::Processing],
            OrderStatus::Shipped => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped],
            OrderStatus::Delivered => [
                OrderStatus::Confirmed, OrderStatus::Processing,
                OrderStatus::Shipped, OrderStatus::Delivered,
            ],
            OrderStatus::Cancelled => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Returned => [
                OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped,
                OrderStatus::Delivered, OrderStatus::Returned,
            ],
        };

        $steps = count($path);
        $span = max(3600, $closedTs - $placedTs);
        $from = OrderStatus::Pending;

        foreach ($path as $index => $step) {
            $at = $this->ts($placedTs + (int) round($span * ($index + 1) / $steps));

            $rows['order_status_histories'][] = [
                'order_id' => $orderId,
                'from_status' => $from->value,
                'to_status' => $step->value,
                'note' => $step === $target ? $note : null,
                'admin_id' => $admins !== [] && $this->chance(80)
                    ? $admins[mt_rand(0, count($admins) - 1)]
                    : null,
                'created_at' => $at,
                'updated_at' => $at,
            ];

            $from = $step;
        }
    }

    /** Reviews grow out of delivered orders, so most of them are verified purchases. */
    private function seedReviews(int $cap): void
    {
        if ($cap < 1) {
            return;
        }

        $admins = DB::table('admins')->pluck('id')->all();
        $rows = ['reviews' => []];
        $cursor = 0;
        $made = 0;
        $bar = $this->bar($cap);

        while ($made < $cap) {
            $batch = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.status', OrderStatus::Delivered->value)
                ->whereNotNull('oi.product_id')
                ->where('oi.id', '>', $cursor)
                ->orderBy('oi.id')
                ->limit(5000)
                ->get(['oi.id', 'oi.product_id', 'o.user_id', 'o.id as order_id', 'o.delivered_at']);

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $item) {
                $cursor = (int) $item->id;

                if (! $this->chance(65)) {          // not every buyer writes one
                    continue;
                }

                [$rating, $title, $comment] = self::REVIEW_COPY[mt_rand(0, count(self::REVIEW_COPY) - 1)];
                $approved = $this->chance(85);
                $writtenAt = $this->ts(strtotime((string) $item->delivered_at) + mt_rand(1, 10) * 86400);

                $rows['reviews'][] = [
                    'product_id' => $item->product_id,
                    'user_id' => $item->user_id,
                    'order_id' => $item->order_id,
                    'rating' => $rating,
                    'title' => $title,
                    'comment' => $comment,
                    'is_approved' => $approved,
                    'approved_at' => $approved ? $writtenAt : null,
                    'approved_by' => $approved && $admins !== []
                        ? $admins[mt_rand(0, count($admins) - 1)]
                        : null,
                    'created_at' => $writtenAt,
                    'updated_at' => $writtenAt,
                ];

                $made++;
                $bar->advance();

                if ($made >= $cap) {
                    break;
                }
            }

            // One review per customer per product: the unique index drops the rest.
            $this->write($rows, ['reviews']);
        }

        $this->write($rows, ['reviews']);
        $bar->finish();
    }

    private function seedCartsAndWishlists(): void
    {
        $variants = $this->variantPool(20000);
        $minProduct = (int) DB::table('products')->min('id');
        $maxProduct = (int) DB::table('products')->max('id');

        if ($variants === [] || $maxProduct === 0) {
            return;
        }

        $cartId = (int) DB::table('carts')->max('id') + 1;
        $cursor = 0;
        $rows = ['carts' => [], 'cart_items' => [], 'wishlists' => []];
        $bar = $this->bar((int) DB::table('users')->where('email', 'like', '%@bulk.test')->count());

        while (true) {
            $users = DB::table('users')
                ->where('email', 'like', '%@bulk.test')
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit(5000)
                ->pluck('id')
                ->all();

            if ($users === []) {
                break;
            }

            foreach ($users as $userId) {
                $cursor = (int) $userId;
                $stamp = $this->ts($this->pastTs(60));

                // A live cart for one shopper in twenty.
                if ($this->chance(5)) {
                    $rows['carts'][] = [
                        'id' => $cartId,
                        'user_id' => $userId,
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ];

                    $picked = [];

                    for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
                        $variant = $variants[mt_rand(0, count($variants) - 1)];

                        if (isset($picked[$variant->id])) {
                            continue;
                        }

                        $picked[$variant->id] = true;

                        $rows['cart_items'][] = [
                            'cart_id' => $cartId,
                            'product_variant_id' => $variant->id,
                            'quantity' => mt_rand(1, 3),
                            'created_at' => $stamp,
                            'updated_at' => $stamp,
                        ];
                    }

                    $cartId++;
                }

                // A wishlist for one in five.
                if ($this->chance(20)) {
                    for ($i = 0, $n = mt_rand(1, 5); $i < $n; $i++) {
                        $rows['wishlists'][] = [
                            'user_id' => $userId,
                            'product_id' => mt_rand($minProduct, $maxProduct),
                            'created_at' => $stamp,
                            'updated_at' => $stamp,
                        ];
                    }
                }

                $bar->advance();
            }

            $this->write($rows, ['carts', 'cart_items', 'wishlists']);
        }

        $this->write($rows, ['carts', 'cart_items', 'wishlists']);
        $bar->finish();
    }

    // --------------------------------------------------------------- lookups

    /**
     * The real catalogue, used as the shape for the clones. Capped so a second
     * run doesn't try to load the previous run's 100K rows into memory.
     *
     * @return list<object>
     */
    private function templates(): array
    {
        $templates = DB::table('products')
            ->where('slug', 'not like', '%-bx%')
            ->orderBy('id')
            ->limit(1000)
            ->get(['id', 'name', 'slug', 'short_description', 'description', 'base_price'])
            ->all();

        if ($templates === []) {
            return [];
        }

        $images = DB::table('product_images')
            ->whereIn('product_id', array_column($templates, 'id'))
            ->orderBy('sort_order')
            ->get(['product_id', 'path'])
            ->groupBy('product_id')
            ->map(fn ($group) => $group->pluck('path')->all())
            ->all();

        foreach ($templates as $template) {
            $template->images = $images[$template->id] ?? [];
        }

        return $templates;
    }

    /** @return list<array{0:int,1:?int,2:string,3:?string}> [gov_id, city_id, gov_name, city_name] */
    private function locations(): array
    {
        return DB::table('cities as c')
            ->join('governorates as g', 'g.id', '=', 'c.governorate_id')
            ->where('g.is_active', true)
            ->get(['g.id as gov_id', 'c.id as city_id', 'g.name as gov_name', 'c.name as city_name'])
            ->map(fn ($row) => [(int) $row->gov_id, (int) $row->city_id, $row->gov_name, $row->city_name])
            ->all();
    }

    /**
     * A random slice of purchasable variants, carrying everything an order line
     * needs to be written without a lookup per row.
     *
     * @return list<object>
     */
    private function variantPool(int $size): array
    {
        $min = (int) DB::table('product_variants')->min('id');
        $max = (int) DB::table('product_variants')->max('id');

        if ($max === 0) {
            return [];
        }

        $ids = [];

        for ($i = 0, $target = min($size, $max - $min + 1); count($ids) < $target && $i < $target * 3; $i++) {
            $ids[mt_rand($min, $max)] = true;
        }

        return DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('attribute_value_product_variant as pivot', 'pivot.product_variant_id', '=', 'v.id')
            ->leftJoin('attribute_values as av', 'av.id', '=', 'pivot.attribute_value_id')
            ->whereIn('v.id', array_keys($ids))
            ->where('v.is_active', true)
            ->where('v.stock_quantity', '>', 0)
            ->whereNull('p.deleted_at')
            ->groupBy('v.id', 'v.product_id', 'v.sku', 'v.price', 'p.name')
            ->selectRaw("v.id, v.product_id, v.sku, v.price, p.name as product_name,
                NULLIF(GROUP_CONCAT(av.value ORDER BY av.id SEPARATOR ' / '), '') as label")
            ->get()
            ->all();
    }

    /** Customers that can actually receive an order, with the address snapshot inlined. */
    private function customerChunk(int $afterUserId, int $limit): array
    {
        return DB::table('addresses as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('governorates as g', 'g.id', '=', 'a.governorate_id')
            ->leftJoin('cities as c', 'c.id', '=', 'a.city_id')
            ->leftJoin('shipping_zones as z', 'z.id', '=', 'g.shipping_zone_id')
            ->where('a.is_default', true)
            ->whereNull('u.deleted_at')
            ->where('a.user_id', '>', $afterUserId)
            ->orderBy('a.user_id')
            ->limit($limit)
            ->get([
                'a.user_id', 'a.governorate_id', 'a.recipient_name', 'a.phone', 'a.alternate_phone',
                'a.street', 'a.building', 'a.floor', 'a.apartment', 'a.landmark',
                'g.name as governorate_name', 'c.name as city_name',
                'z.cost as zone_cost', 'z.free_shipping_threshold as zone_threshold',
            ])
            ->all();
    }

    /**
     * Coupons safe to hand out tens of thousands of times: no usage cap, so the
     * generated orders don't make the hand-seeded ones look over-redeemed.
     *
     * @return list<object>
     */
    private function bulkCoupons(): array
    {
        $definitions = [
            ['BULK10', 'percent', 10, 300, 150],
            ['BULK20', 'percent', 20, 700, 400],
            ['BULK50', 'fixed', 50, 400, null],
            ['BULK100', 'fixed', 100, 1200, null],
        ];

        foreach ($definitions as [$code, $type, $value, $minTotal, $maxDiscount]) {
            DB::table('coupons')->upsert([[
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'min_order_total' => $minTotal,
                'max_discount' => $maxDiscount,
                'usage_limit' => null,
                'usage_limit_per_user' => null,
                'starts_at' => $this->ts($this->now - 1095 * 86400),
                'expires_at' => $this->ts($this->now + 365 * 86400),
                'is_active' => true,
                'created_at' => $this->ts($this->now),
                'updated_at' => $this->ts($this->now),
            ]], ['code'], ['type', 'value', 'min_order_total', 'max_discount', 'expires_at', 'is_active']);
        }

        return DB::table('coupons')
            ->whereIn('code', array_column($definitions, 0))
            ->get(['id', 'code', 'type', 'value', 'min_order_total', 'max_discount'])
            ->all();
    }

    /** Highest sequence already used per ORD-<date>, so new numbers continue it. */
    private function orderNumberSequences(): array
    {
        return DB::table('orders')
            ->selectRaw('SUBSTRING(order_number, 5, 8) as ymd')
            ->selectRaw('MAX(CAST(SUBSTRING(order_number, 14) AS UNSIGNED)) as seq')
            ->groupBy('ymd')
            ->pluck('seq', 'ymd')
            ->map(fn ($seq) => (int) $seq)
            ->all();
    }

    // --------------------------------------------------------------- helpers

    private function addressRow(int $userId, string $name, array $locations, string $label, bool $default, string $created): array
    {
        [$governorateId, $cityId] = $locations[mt_rand(0, count($locations) - 1)];

        return [
            'user_id' => $userId,
            'governorate_id' => $governorateId,
            'city_id' => $cityId,
            'label' => $label,
            'recipient_name' => $name,
            'phone' => '019'.str_pad((string) $userId, 8, '0', STR_PAD_LEFT),
            'alternate_phone' => $this->chance(20) ? '0100'.mt_rand(1000000, 9999999) : null,
            'street' => self::STREETS[mt_rand(0, count(self::STREETS) - 1)],
            'building' => (string) mt_rand(1, 90),
            'floor' => (string) mt_rand(1, 12),
            'apartment' => (string) mt_rand(1, 40),
            'landmark' => self::LANDMARKS[mt_rand(0, count(self::LANDMARKS) - 1)],
            'is_default' => $default,
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }

    /** @return array{0:OrderStatus,1:?string} */
    private function pickLifecycle(): array
    {
        $roll = mt_rand(1, 100);
        $cumulative = 0;

        foreach (self::LIFECYCLES as [$weight, $status, $note]) {
            $cumulative += $weight;

            if ($roll <= $cumulative) {
                return [$status, $note];
            }
        }

        return [OrderStatus::Pending, null];
    }

    /** Most customers order once or twice; a few are regulars. */
    private function ordersPerCustomer(): int
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 55 => 1,
            $roll <= 78 => 2,
            $roll <= 90 => 3,
            $roll <= 97 => 5,
            default => 12,
        };
    }

    private function discountFor(object $coupon, float $subtotal): float
    {
        if ($coupon->min_order_total !== null && $subtotal < (float) $coupon->min_order_total) {
            return 0.0;
        }

        $discount = $coupon->type === 'percent'
            ? $subtotal * (float) $coupon->value / 100
            : (float) $coupon->value;

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }

    /** Inserts the buffers in dependency order and empties them. */
    private function write(array &$rows, array $tables): void
    {
        DB::transaction(function () use (&$rows, $tables): void {
            foreach ($tables as $table) {
                if (empty($rows[$table])) {
                    continue;
                }

                foreach (array_chunk($rows[$table], $this->chunk) as $batch) {
                    DB::table($table)->insertOrIgnore($batch);
                }

                $rows[$table] = [];
            }
        });
    }

    /**
     * Takes the generated set back out. Hand-seeded rows are left alone, except
     * that an order a demo customer placed against a generated product keeps
     * its line snapshot and loses the product link — which is the behaviour the
     * order_items FKs were designed for.
     */
    private function purge(): void
    {
        $this->components->warn('Removing previously generated bulk rows…');

        // Items, histories and redemptions cascade with the order. The ids are
        // fetched first: MySQL refuses a subquery on the table being deleted.
        while (true) {
            $orderIds = DB::table('orders as o')
                ->join('users as u', 'u.id', '=', 'o.user_id')
                ->where('u.email', 'like', '%@bulk.test')
                ->limit(5000)
                ->pluck('o.id')
                ->all();

            if ($orderIds === []) {
                break;
            }

            DB::table('orders')->whereIn('id', $orderIds)->delete();
        }

        // Addresses, carts, wishlists and reviews cascade with the customer.
        while (DB::table('users')->where('email', 'like', '%@bulk.test')->limit(5000)->delete() > 0) {
            // keep going
        }

        // Images, variants and attribute links cascade with the product.
        while (DB::table('products')->where('slug', 'like', '%-bx%')->limit(5000)->delete() > 0) {
            // keep going
        }

        DB::table('coupons')->whereIn('code', ['BULK10', 'BULK20', 'BULK50', 'BULK100'])->delete();
    }

    private function phase(string $label, callable $work): void
    {
        $this->newLine();
        $this->components->info("Generating {$label}…");

        $started = microtime(true);
        $work();

        $this->newLine();
        $this->line("  <fg=gray>{$label} done in {$this->elapsed($started)}</>");
    }

    private function counts(): void
    {
        $tables = [
            'users', 'addresses', 'products', 'product_variants', 'product_images',
            'orders', 'order_items', 'order_status_histories', 'reviews',
            'carts', 'cart_items', 'wishlists', 'coupon_redemptions',
        ];

        $this->table(
            ['table', 'rows'],
            array_map(
                fn (string $table) => [$table, number_format(DB::table($table)->count())],
                $tables,
            ),
        );
    }

    private function bar(int $max): ProgressBar
    {
        $bar = $this->output->createProgressBar(max(1, $max));
        $bar->setRedrawFrequency(max(1, (int) ($max / 100)));
        $bar->start();

        return $bar;
    }

    private function pastTs(int $maxDays): int
    {
        return $this->now - mt_rand(0, $maxDays * 86400);
    }

    private function ts(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    private function elapsed(float $started): string
    {
        $seconds = microtime(true) - $started;

        return $seconds < 60
            ? sprintf('%.1fs', $seconds)
            : sprintf('%dm %02ds', intdiv((int) $seconds, 60), (int) $seconds % 60);
    }
}
