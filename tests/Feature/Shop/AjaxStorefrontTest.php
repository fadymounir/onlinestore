<?php

namespace Tests\Feature\Shop;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Coupon;
use App\Enums\CouponType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\AttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AJAX layer's server half.
 *
 * Every one of these routes has to answer twice: a redirect for a plain form
 * post, and JSON for the same request carrying `X-Fragment`. The storefront is
 * progressive enhancement, so a regression in either half is a regression.
 */
class AjaxStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    /**
     * Exactly the headers resources/js/app.js sends.
     *
     * Accept matters as much as X-Fragment: it is what makes Laravel answer a
     * failed validation with 422 JSON instead of a redirect, so a test that
     * omitted it would not be exercising what the browser does.
     */
    private const FRAGMENT = [
        'X-Fragment' => '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttributeSeeder::class);

        $category = Category::create(['name' => 'خواتم', 'slug' => 'rings']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'خاتم استانلس',
            'slug' => 'steel-ring',
            'base_price' => 150,
            'published_at' => now()->subDay(),
        ]);

        $this->variant = $this->product->variants()->create([
            'sku' => 'RING-7', 'price' => 150, 'stock_quantity' => 10,
        ]);
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

    /*
    |---------------------------------------------------------------------------
    | Cart
    |---------------------------------------------------------------------------
    */

    public function test_adding_to_the_cart_returns_a_toast_and_the_new_count(): void
    {
        $response = $this->withHeaders(self::FRAGMENT)
            ->post(route('cart.add', $this->variant), ['quantity' => 2]);

        $response->assertOk()
            ->assertJsonPath('level', 'success')
            ->assertJsonPath('cartCount', 2)
            ->assertJsonPath('message', 'تمت إضافة المنتج إلى السلة.');

        // No swap: the customer is on the product page, which has nothing to
        // replace — the toast and the header badge are the whole response.
        $this->assertSame([], $response->json('swap'));
    }

    public function test_adding_to_the_cart_without_the_header_still_redirects(): void
    {
        $this->post(route('cart.add', $this->variant), ['quantity' => 1])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');
    }

    public function test_an_out_of_stock_variant_is_refused_as_json(): void
    {
        $this->variant->update(['stock_quantity' => 0]);

        $this->withHeaders(self::FRAGMENT)
            ->post(route('cart.add', $this->variant))
            ->assertOk()
            ->assertJsonPath('level', 'error')
            ->assertJsonPath('message', 'هذا المقاس غير متاح حالياً.');
    }

    public function test_updating_a_quantity_returns_the_rerendered_cart_panel(): void
    {
        $this->withHeaders(self::FRAGMENT)->post(route('cart.add', $this->variant), ['quantity' => 1]);

        $response = $this->withHeaders(self::FRAGMENT)
            ->patch(route('cart.update', $this->variant), ['quantity' => 3]);

        $response->assertOk()->assertJsonPath('cartCount', 3);

        // The panel carries the recalculated total, which is the whole point of
        // re-rendering server-side instead of patching numbers in JavaScript.
        $panel = $response->json('swap.#cart-panel');
        $this->assertStringContainsString('450.00', $panel);
        $this->assertStringContainsString('ملخص الطلب', $panel);
    }

    public function test_removing_a_line_returns_the_empty_cart_panel(): void
    {
        $this->withHeaders(self::FRAGMENT)->post(route('cart.add', $this->variant), ['quantity' => 1]);

        $response = $this->withHeaders(self::FRAGMENT)
            ->delete(route('cart.remove', $this->variant));

        $response->assertOk()->assertJsonPath('cartCount', 0);
        $this->assertStringContainsString('سلتك فارغة', $response->json('swap.#cart-panel'));
    }

    public function test_a_validation_error_comes_back_as_json(): void
    {
        $this->withHeaders(self::FRAGMENT)
            ->patch(route('cart.update', $this->variant), ['quantity' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    /*
    |---------------------------------------------------------------------------
    | Coupons
    |---------------------------------------------------------------------------
    */

    public function test_a_coupon_applies_and_the_panel_shows_the_discount(): void
    {
        $user = $this->customer();

        Coupon::create([
            'code' => 'EID25',
            'type' => CouponType::Percent,
            'value' => 25,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $this->withHeaders(self::FRAGMENT)->post(route('cart.add', $this->variant), ['quantity' => 2]);

        $response = $this->withHeaders(self::FRAGMENT)
            ->post(route('cart.coupon.apply'), ['code' => 'eid25']);

        $response->assertOk()->assertJsonPath('level', 'success');

        $panel = $response->json('swap.#cart-panel');
        $this->assertStringContainsString('EID25', $panel);
        $this->assertStringContainsString('خصم', $panel);
    }

    public function test_an_invalid_coupon_is_reported_without_breaking_the_panel(): void
    {
        $this->actingAs($this->customer());
        $this->withHeaders(self::FRAGMENT)->post(route('cart.add', $this->variant), ['quantity' => 1]);

        $response = $this->withHeaders(self::FRAGMENT)
            ->post(route('cart.coupon.apply'), ['code' => 'NOPE']);

        $response->assertOk()->assertJsonPath('level', 'error');
        // The panel still comes back, so a rejected code cannot blank the cart.
        $this->assertStringContainsString('ملخص الطلب', $response->json('swap.#cart-panel'));
    }

    public function test_a_guest_is_told_to_sign_in_for_a_coupon(): void
    {
        $this->withHeaders(self::FRAGMENT)
            ->post(route('cart.coupon.apply'), ['code' => 'EID25'])
            ->assertOk()
            ->assertJsonPath('level', 'error')
            ->assertJsonPath('message', 'سجّل الدخول أولاً لاستخدام الكوبون.');
    }

    /*
    |---------------------------------------------------------------------------
    | Wishlist
    |---------------------------------------------------------------------------
    */

    public function test_the_wishlist_toggle_reports_which_way_it_went(): void
    {
        $this->actingAs($this->customer());

        $this->withHeaders(self::FRAGMENT)
            ->post(route('wishlist.toggle', $this->product))
            ->assertOk()
            ->assertJsonPath('inWishlist', true)
            ->assertJsonPath('message', 'أُضيف إلى المفضّلة.');

        $this->withHeaders(self::FRAGMENT)
            ->post(route('wishlist.toggle', $this->product))
            ->assertOk()
            ->assertJsonPath('inWishlist', false)
            ->assertJsonPath('message', 'أُزيل من المفضّلة.');
    }

    /*
    |---------------------------------------------------------------------------
    | Catalog
    |---------------------------------------------------------------------------
    */

    public function test_the_catalog_returns_only_the_grid_and_the_count(): void
    {
        $response = $this->withHeaders(self::FRAGMENT)->get(route('catalog'));

        $response->assertOk()
            ->assertJsonStructure(['swap' => ['#catalog-results', '#catalog-count'], 'url'])
            ->assertJsonPath('swap.#catalog-count', '1 منتج');

        $grid = $response->json('swap.#catalog-results');
        $this->assertStringContainsString('خاتم استانلس', $grid);

        // A fragment must not drag the layout along with it.
        $this->assertStringNotContainsString('<html', $grid);
        $this->assertStringNotContainsString('</footer>', $grid);
    }

    public function test_the_fragment_url_preserves_the_filters_for_history(): void
    {
        $url = $this->withHeaders(self::FRAGMENT)
            ->get(route('catalog', ['sort' => 'price_asc', 'in_stock' => 1]))
            ->assertOk()
            ->json('url');

        // Compared as parsed parameters, not as a string: fullUrl() is free to
        // order the query however it likes, and the browser only needs the
        // filters to survive into the history entry.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        ksort($query);

        $this->assertSame(['in_stock' => '1', 'sort' => 'price_asc'], $query);
        $this->assertSame(route('catalog'), strtok($url, '?'));
    }

    public function test_a_category_page_also_answers_with_a_fragment(): void
    {
        $response = $this->withHeaders(self::FRAGMENT)
            ->get(route('category', Category::where('slug', 'rings')->sole()));

        $response->assertOk()->assertJsonPath('swap.#catalog-count', '1 منتج');
        $this->assertStringContainsString('خاتم استانلس', $response->json('swap.#catalog-results'));
    }

    public function test_the_catalog_still_renders_a_full_page_without_the_header(): void
    {
        $this->get(route('catalog'))
            ->assertOk()
            ->assertSee('id="catalog-results"', escape: false)
            ->assertSee('data-ajax-nav', escape: false)
            ->assertSee('خاتم استانلس');
    }
}
