<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page-visit layer's server half.
 *
 * A click in the storefront no longer loads a document: resources/js/app.js
 * asks for the same route with `X-Page-Visit`, and layouts/shop-shell.blade.php
 * answers with just the regions a navigation can change. Two things have to
 * hold for that to be safe, and both are asserted here.
 *
 *   The shell is a strict subset.  Every region it sends exists in the full
 *   page under the same id, and it sends nothing the full page does not have.
 *   Otherwise a shopper who arrives by clicking sees a different store from
 *   one who arrives by typing the URL.
 *
 *   The full page never stops working.  Drop the header and the same route
 *   still renders a document, because that is what a visitor with JavaScript
 *   off — and every crawler — gets.
 */
class PageVisitTest extends TestCase
{
    use RefreshDatabase;

    /** Exactly what app.js sends for a navigation from a guest's home page. */
    private const VISIT = [
        'X-Page-Visit' => '1',
        'X-Page-Chrome' => 'guest',
        'X-Page-Rail' => '1',
    ];

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'خواتم', 'slug' => 'rings']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'خاتم استانلس',
            'slug' => 'steel-ring',
            'base_price' => 150,
            'published_at' => now()->subDay(),
        ]);

        ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'RING-7',
            'price' => 150,
            'stock_quantity' => 10,
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
    | The shape of a shell
    |---------------------------------------------------------------------------
    */

    public function test_a_visit_returns_the_regions_and_not_the_document(): void
    {
        $html = $this->withHeaders(self::VISIT)->get(route('cart.index'))
            ->assertOk()
            ->assertSee('data-page-shell', escape: false)
            ->assertSee('data-shell-region="#site-main"', escape: false)
            ->assertSee('سلة الشراء')
            ->getContent();

        // The chrome around the page is already on screen. Sending it again is
        // the difference between a 6KB navigation and a 53KB one.
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('</footer>', $html);
        $this->assertStringNotContainsString('app-preloader', $html);
        $this->assertStringNotContainsString('<title>', $html);
    }

    public function test_the_shell_carries_what_the_browser_cannot_work_out_itself(): void
    {
        $html = $this->withHeaders(self::VISIT)->get(route('product', $this->product))->getContent();

        // The title belongs in the tab and in the screen reader's announcement;
        // the count repaints a badge that lives outside every region sent.
        $this->assertStringContainsString('data-title="خاتم استانلس —', $html);
        $this->assertStringContainsString('data-cart-count="0"', $html);
        $this->assertStringContainsString('data-chrome="guest"', $html);
    }

    public function test_every_region_the_shell_sends_exists_in_the_full_page(): void
    {
        $page = $this->get(route('catalog'))->getContent();

        // Signed in and with no rail on screen, so the shell sends everything
        // it is capable of sending — the widest the contract ever gets.
        $shell = $this->actingAs($this->customer())
            ->withHeaders(['X-Page-Visit' => '1', 'X-Page-Chrome' => 'nobody', 'X-Page-Rail' => '0'])
            ->get(route('catalog'))
            ->getContent();

        preg_match_all('/data-shell-region="([^"]+)"/', $shell, $matches);

        $this->assertNotEmpty($matches[1], 'the shell sent no regions at all');

        foreach ($matches[1] as $selector) {
            $this->assertStringContainsString(
                'id="'.ltrim($selector, '#').'"',
                $page,
                "the full page has nowhere to put {$selector}",
            );
        }
    }

    /*
    |---------------------------------------------------------------------------
    | What the shell leaves out, and why
    |---------------------------------------------------------------------------
    */

    public function test_the_chrome_is_left_alone_while_the_visitor_is_the_same(): void
    {
        $this->actingAs($user = $this->customer());

        $unchanged = $this->withHeaders(['X-Page-Visit' => '1', 'X-Page-Chrome' => (string) $user->id])
            ->get(route('catalog'))
            ->getContent();

        $this->assertStringNotContainsString('data-shell-region="#site-header"', $unchanged);
        $this->assertStringNotContainsString('data-shell-region="#site-footer"', $unchanged);
    }

    public function test_signing_in_re_sends_the_header_and_the_footer(): void
    {
        $this->actingAs($this->customer());

        // 'guest' is what the document on screen was rendered as: the account
        // menu and the footer's account column are both now wrong.
        $html = $this->withHeaders(['X-Page-Visit' => '1', 'X-Page-Chrome' => 'guest'])
            ->get(route('catalog'))
            ->getContent();

        $this->assertStringContainsString('data-shell-region="#site-header"', $html);
        $this->assertStringContainsString('data-shell-region="#site-footer"', $html);
        $this->assertStringContainsString('منى عبد الرحمن', $html);
    }

    public function test_the_phone_rail_is_only_sent_when_it_appears_or_goes_away(): void
    {
        // Catalogue to catalogue: the rail is up in both, and identical.
        $this->assertStringNotContainsString(
            'data-shell-region="#site-rail"',
            $this->withHeaders(self::VISIT)->get(route('catalog'))->getContent(),
        );

        // Catalogue to cart: it has to come down, so the emptiness is sent.
        $this->assertStringContainsString(
            'data-shell-region="#site-rail"',
            $this->withHeaders(self::VISIT)->get(route('cart.index'))->getContent(),
        );
    }

    public function test_one_url_tells_a_cache_it_has_two_answers(): void
    {
        $this->get(route('catalog'))->assertHeader('Vary', 'X-Page-Visit, X-Fragment');
    }

    /*
    |---------------------------------------------------------------------------
    | Actions that used to reload the page
    |---------------------------------------------------------------------------
    */

    public function test_a_post_lands_on_the_shell_of_wherever_it_redirected(): void
    {
        $this->customer();

        // What fetch does on its own: follow the redirect, carrying the header.
        $response = $this->withHeaders(self::VISIT)
            ->followingRedirects()
            ->post(route('login'), ['email' => 'mona@example.com', 'password' => 'password']);

        $response->assertOk()->assertSee('data-page-shell', escape: false);

        // Signed in now, so the header the browser is showing is out of date
        // and the shell says so without being asked twice.
        $response->assertSee('data-shell-region="#site-header"', escape: false);
    }

    public function test_a_rejected_form_comes_back_as_the_same_page_with_its_errors(): void
    {
        $this->customer();

        $this->withHeaders(self::VISIT)
            ->followingRedirects()
            ->post(route('login'), ['email' => 'mona@example.com', 'password' => 'wrong'])
            ->assertOk()
            ->assertSee('data-page-shell', escape: false)
            // The alert lives inside <main>, so it travels with the region that
            // is replaced — there is nothing for the client to render itself.
            ->assertSee('من فضلك صحّح الآتي');
    }

    /*
    |---------------------------------------------------------------------------
    | With JavaScript off
    |---------------------------------------------------------------------------
    */

    public function test_the_same_routes_still_render_whole_documents(): void
    {
        foreach ([route('home'), route('catalog'), route('product', $this->product), route('cart.index')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<html lang="ar" dir="rtl">', escape: false)
                ->assertSee('id="site-main"', escape: false)
                ->assertSee('id="site-header"', escape: false)
                ->assertSee('id="site-footer"', escape: false)
                ->assertDontSee('data-page-shell', escape: false);
        }
    }
}
