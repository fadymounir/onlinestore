<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product listings without a pager.
 *
 * The grid loads as the shopper scrolls: app.js follows the link underneath it
 * before it reaches the bottom of the screen, and the server answers with the
 * next twelve cards to add rather than a page to replace. Two halves have to
 * agree for that to work, and both are asserted here — the ids the response
 * names must be the ids the page renders.
 *
 * The link is a plain <a href> to the next page, so the listing is still
 * walkable with JavaScript off. That is the only reason the numbered pager
 * could be dropped, so it is tested too.
 */
class EndlessListingTest extends TestCase
{
    use RefreshDatabase;

    private const APPEND = [
        'X-Fragment' => '1',
        'X-Fragment-Mode' => 'append',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'خواتم', 'slug' => 'rings']);

        // The catalogue pages at 12, so 15 is two pages with a short second.
        foreach (range(1, 15) as $i) {
            $this->stocked($category, "ring-{$i}", "خاتم رقم {$i}", 100 + $i);
        }
    }

    /** A product the shop can actually send: published, active, with stock. */
    private function stocked(Category $category, string $slug, string $name, float $price): Product
    {
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'base_price' => $price,
            'published_at' => now()->subDay(),
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($slug),
            'price' => $price,
            'stock_quantity' => 5,
        ]);

        return $product->refresh();
    }

    /*
    |---------------------------------------------------------------------------
    | The listing
    |---------------------------------------------------------------------------
    */

    public function test_the_catalogue_has_no_pager_and_a_link_to_carry_on(): void
    {
        $this->get(route('catalog'))
            ->assertOk()
            ->assertDontSee('التنقل بين الصفحات')
            ->assertSee('id="catalog-grid"', escape: false)
            ->assertSee('id="catalog-more"', escape: false)
            // The link names the grid its cards are headed for, so app.js can
            // show the placeholders where the real ones will land.
            ->assertSee('data-load-more="#catalog-grid"', escape: false)
            ->assertSee('عرض المزيد');
    }

    public function test_the_link_is_a_working_next_page_without_javascript(): void
    {
        // Nothing about it is JavaScript-only: following it in a browser with
        // none lands on page two, which is the whole reason the pager could go.
        // Counted rather than named: fifteen products published in the same
        // tick have no stable order to assert a particular one against.
        $page = $this->get(route('catalog', ['page' => 2]))->assertOk()->getContent();

        $this->assertSame(3, substr_count($page, '<article'), '15 products at 12 a page leaves 3');
    }

    public function test_the_last_page_offers_nothing_more(): void
    {
        $this->get(route('catalog', ['page' => 2]))
            ->assertOk()
            ->assertDontSee('data-load-more', escape: false)
            ->assertSee('وصلت إلى نهاية النتائج');
    }

    /*
    |---------------------------------------------------------------------------
    | Scrolling
    |---------------------------------------------------------------------------
    */

    public function test_scrolling_asks_for_cards_to_add_not_a_page_to_replace(): void
    {
        $response = $this->withHeaders(self::APPEND)
            ->get(route('catalog', ['page' => 2]))
            ->assertOk();

        // Cards are added to the grid; the link that asked for them is
        // replaced. Nothing else moves, because nothing else changed.
        $this->assertSame(['#catalog-grid'], array_keys($response->json('append')));
        $this->assertSame(['#catalog-more'], array_keys($response->json('swap')));

        $cards = $response->json('append.#catalog-grid');
        $this->assertSame(3, substr_count($cards, '<article'));

        // The fragment is cards and nothing around them — a second grid would
        // land inside the first, and the layout inside that.
        $this->assertStringNotContainsString('id="catalog-grid"', $cards);
        $this->assertStringNotContainsString('<html', $cards);
    }

    public function test_the_end_of_the_listing_takes_the_link_away(): void
    {
        $slot = $this->withHeaders(self::APPEND)
            ->get(route('catalog', ['page' => 2]))
            ->assertOk()
            ->json('swap.#catalog-more');

        // Sent empty-handed rather than omitted: the client has to be able to
        // remove the link it just followed, or it would follow it forever.
        $this->assertStringNotContainsString('data-load-more', $slot);
        $this->assertStringContainsString('وصلت إلى نهاية النتائج', $slot);
    }

    public function test_the_next_page_keeps_the_filters_it_was_reached_with(): void
    {
        $html = $this->get(route('catalog', ['sort' => 'price_desc', 'min' => 100]))
            ->assertOk()
            ->getContent();

        preg_match('/data-load-more="[^"]*" *\n?\s*class|href="([^"]+)" rel="next"/', $html, $m);

        $this->assertStringContainsString('sort=price_desc', $m[1]);
        $this->assertStringContainsString('min=100', $m[1]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'منى عبد الرحمن',
            'email' => 'mona@example.com',
            'phone' => '01011234501',
            'password' => 'password',
        ]);
    }

    public function test_the_wishlist_grid_works_the_same_way(): void
    {
        $customer = $this->customer();

        $customer->wishlist()->sync(Product::pluck('id'));

        $this->actingAs($customer)
            ->get(route('wishlist'))
            ->assertOk()
            ->assertDontSee('التنقل بين الصفحات')
            ->assertSee('data-load-more="#wishlist-grid"', escape: false);

        $response = $this->actingAs($customer)
            ->withHeaders(self::APPEND)
            ->get(route('wishlist', ['page' => 2]))
            ->assertOk();

        $this->assertSame(['#wishlist-grid'], array_keys($response->json('append')));
        $this->assertSame(['#wishlist-more'], array_keys($response->json('swap')));
    }

    /*
    |---------------------------------------------------------------------------
    | Only what the shop can actually send
    |---------------------------------------------------------------------------
    */

    /** A product named so that no other product's name contains it. */
    private function soldOut(): Product
    {
        $product = $this->stocked(
            Category::where('slug', 'rings')->sole(),
            'pearl-necklace',
            'عقد لؤلؤ نادر',
            900,
        );

        $product->variants()->update(['stock_quantity' => 0]);
        Product::recountStock([$product->id]);

        return $product;
    }

    public function test_a_sold_out_product_leaves_every_listing(): void
    {
        $product = $this->soldOut();

        // It is featured, too: nothing gets a pass for being promoted.
        $product->update(['is_featured' => true]);

        foreach ([route('catalog'), route('home'), route('category', 'rings')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('عقد لؤلؤ نادر');
        }

        // Search too — it is the same query underneath.
        $this->get(route('catalog', ['q' => 'لؤلؤ']))
            ->assertOk()
            ->assertDontSee('عقد لؤلؤ نادر')
            ->assertSee('لا توجد منتجات مطابقة');
    }

    public function test_but_its_own_page_still_opens(): void
    {
        // A link in someone's messages must not turn into a 404 the moment the
        // last one sells: the page opens and says the stock is gone.
        $this->get(route('product', $this->soldOut()))
            ->assertOk()
            ->assertSee('عقد لؤلؤ نادر')
            ->assertSee('نفدت الكمية');
    }

    public function test_a_sold_out_product_leaves_the_wishlist_but_not_the_wishlist(): void
    {
        $customer = $this->customer();
        $soldOut = $this->soldOut();

        $customer->wishlist()->sync([$soldOut->id, Product::where('slug', 'ring-1')->sole()->id]);

        $this->actingAs($customer)
            ->get(route('wishlist'))
            ->assertOk()
            ->assertDontSee('عقد لؤلؤ نادر')
            ->assertSee('خاتم رقم 1');

        // Hidden, not removed: the row is still there, so the day it is
        // restocked the customer finds it waiting rather than having to
        // remember what they had saved.
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $customer->id,
            'product_id' => $soldOut->id,
        ]);
    }

    public function test_a_wishlist_of_nothing_available_says_so_rather_than_looking_empty(): void
    {
        $customer = $this->customer();

        $customer->wishlist()->sync([$this->soldOut()->id]);

        $this->actingAs($customer)
            ->get(route('wishlist'))
            ->assertOk()
            // "You have saved nothing" would be a plain untruth here.
            ->assertDontSee('لا منتجات في المفضّلة')
            ->assertSee('لا شيء من مفضّلتك متاح حالياً');
    }

    public function test_an_untouched_wishlist_still_says_it_is_empty(): void
    {
        $this->actingAs($this->customer())
            ->get(route('wishlist'))
            ->assertOk()
            ->assertSee('لا منتجات في المفضّلة');
    }

    public function test_a_product_taken_off_the_shop_leaves_the_wishlist_too(): void
    {
        $customer = $this->customer();
        $product = Product::where('slug', 'ring-1')->sole();

        $customer->wishlist()->sync([$product->id]);
        $product->update(['is_active' => false]);

        // Its card used to link to a 404: ProductController refuses an
        // inactive product, and the wishlist was the one listing not asking.
        $this->actingAs($customer)
            ->get(route('wishlist'))
            ->assertOk()
            ->assertDontSee('خاتم رقم 1');
    }

    public function test_a_sold_out_product_is_not_offered_as_related(): void
    {
        $this->soldOut();

        $this->get(route('product', Product::where('slug', 'ring-1')->sole()))
            ->assertOk()
            ->assertDontSee('عقد لؤلؤ نادر');
    }
}
