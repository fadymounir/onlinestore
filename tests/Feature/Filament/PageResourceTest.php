<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ContentPages\Pages\CreatePage;
use App\Filament\Resources\ContentPages\Pages\EditPage;
use App\Models\Admin;
use App\Models\Page;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel half of the content pages — the point of the feature is that staff
 * can publish and amend this copy without a deploy, so the round trip from the
 * admin form to the storefront is what matters here.
 */
class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminSeeder::class);
        $this->actingAs(Admin::where('email', 'admin@admin.com')->sole(), 'admin');
    }

    public function test_every_page_screen_renders(): void
    {
        $page = Page::create([
            'title' => 'من نحن',
            'slug' => 'about-us',
            'content' => '<p>متجر مصري.</p>',
        ]);

        $this->get('/admin/pages')->assertOk()->assertSee('الصفحات');
        $this->get('/admin/pages/create')->assertOk()->assertSee('محتوى الصفحة');
        $this->get("/admin/pages/{$page->id}/edit")->assertOk()->assertSee('الظهور والأرشفة');
    }

    public function test_an_admin_can_publish_a_page_that_the_storefront_then_serves(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'الشروط والأحكام',
                'slug' => 'terms',
                'content' => '<p>الدفع عند الاستلام.</p>',
                'is_active' => true,
                'show_in_footer' => true,
                'sort_order' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'terms')->sole();

        $this->get(route('page', $page))
            ->assertOk()
            ->assertSee('الدفع عند الاستلام.', false);
    }

    public function test_the_slug_must_be_url_safe_and_unique(): void
    {
        Page::create(['title' => 'من نحن', 'slug' => 'about-us', 'content' => '<p>.</p>']);

        // Arabic titles do not produce a slug, so the field is typed by hand —
        // which is exactly why it is validated rather than trusted.
        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'صفحة', 'slug' => 'من نحن', 'content' => '<p>.</p>'])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'صفحة', 'slug' => 'about-us', 'content' => '<p>.</p>'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_unpublishing_from_the_panel_takes_the_page_off_the_storefront(): void
    {
        $page = Page::create(['title' => 'من نحن', 'slug' => 'about-us', 'content' => '<p>.</p>']);

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get(route('page', $page))->assertNotFound();
    }
}
