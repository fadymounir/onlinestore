<?php

namespace Tests\Feature\Shop;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-authored content pages — about, privacy, terms.
 *
 * The point of these living in the database is that staff control them without
 * a deploy, so the tests cover the two levers the panel exposes: `is_active`
 * (reachable at all) and `show_in_footer` (linked from the chrome).
 */
class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $overrides = []): Page
    {
        return Page::create([
            'title' => 'من نحن',
            'slug' => 'about-us',
            'content' => '<p>متجر مصري للإكسسوارات.</p>',
            ...$overrides,
        ]);
    }

    public function test_a_published_page_renders_its_content(): void
    {
        $page = $this->page(['meta_description' => 'وصف قصير للصفحة.']);

        $this->get(route('page', $page))
            ->assertOk()
            ->assertSee('من نحن')
            // Unescaped: the rich editor's markup has to reach the browser as
            // markup, not as visible tags.
            ->assertSee('<p>متجر مصري للإكسسوارات.</p>', false)
            ->assertSee('وصف قصير للصفحة.');
    }

    public function test_the_slug_is_the_route_key(): void
    {
        $this->page();

        $this->get('/page/about-us')->assertOk();
        $this->get('/page/no-such-page')->assertNotFound();
    }

    public function test_an_unpublished_page_is_gone_not_merely_unlisted(): void
    {
        // A bookmark or a search result outlives the footer link, so switching
        // a page off has to answer 404 rather than serve the old copy.
        $page = $this->page(['is_active' => false]);

        $this->get(route('page', $page))->assertNotFound();
    }

    public function test_footer_pages_are_linked_from_every_storefront_page(): void
    {
        $this->page(['title' => 'الشروط والأحكام', 'slug' => 'terms', 'sort_order' => 2]);
        $this->page(['title' => 'سياسة الخصوصية', 'slug' => 'privacy', 'menu_title' => 'الخصوصية', 'sort_order' => 1]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('معلومات')
            // The short label wins where it is set, so a long legal title does
            // not stretch the footer column.
            ->assertSee('الخصوصية')
            ->assertSee(route('page', 'terms'));

        // sort_order, not insertion order.
        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, route('page', 'terms')),
            strpos($body, route('page', 'privacy')),
        );
    }

    public function test_a_page_can_be_published_but_kept_out_of_the_footer(): void
    {
        $page = $this->page(['slug' => 'shipping', 'title' => 'سياسة الشحن', 'show_in_footer' => false]);

        $this->get(route('page', $page))->assertOk();
        $this->get(route('home'))->assertDontSee(route('page', $page));
    }

    public function test_an_inactive_page_is_dropped_from_the_footer(): void
    {
        $page = $this->page(['is_active' => false]);

        $this->get(route('home'))->assertDontSee(route('page', $page));
    }
}
