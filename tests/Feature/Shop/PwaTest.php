<?php

namespace Tests\Feature\Shop;

use App\Console\Commands\BuildPwaManifest;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The installable storefront: the manifest a browser reads, the meta tags iOS
 * reads instead, the offline page the worker falls back to, and the promises
 * the worker itself makes about what it will not keep.
 *
 * The service worker is JavaScript and no PHP test can run it. What is checked
 * here is the half that can be: that the files it needs exist at the URLs it
 * asks for, and that its source still says what the rest of this design
 * assumes — that nothing authenticated is ever cached, and that the panel is
 * left alone. Those are the assertions that would catch someone loosening the
 * allowlist without meaning to.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    /* ------------------------------------------------------------ manifest -- */

    public function test_the_committed_manifest_is_valid_and_installable(): void
    {
        // public_path, not config('pwa.manifest'): this is the file the server
        // actually serves, and the test environment points that config at a
        // throwaway copy so a save in another test cannot rewrite this one.
        $path = public_path('manifest.json');

        $this->assertFileExists($path, 'public/manifest.json is missing — run `php artisan pwa:manifest`.');

        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        // The fields a browser requires before it will offer to install.
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $manifest['theme_color']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $manifest['background_color']);

        // start_url has to be inside the scope, or the installed app opens in
        // the browser instead of its own window.
        $this->assertStringStartsWith($manifest['scope'], $manifest['start_url']);

        // Arabic, right to left, like every other page of this shop.
        $this->assertSame('ar', $manifest['lang']);
        $this->assertSame('rtl', $manifest['dir']);
    }

    public function test_the_manifest_declares_both_icon_purposes_and_every_file_exists(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $sizes = [];

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(
                public_path(ltrim($icon['src'], '/')),
                "{$icon['src']} is declared in the manifest but not on disk — run `npm run logo`.",
            );

            // A declared size that does not match the file is worse than no
            // icon: the browser scales to what it was promised, and the home
            // screen gets a blurred badge.
            [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$width}x{$height}");

            $sizes[$icon['purpose']][] = $icon['sizes'];
        }

        // 192 and 512 of each: `any` for the tab and the desktop shortcut,
        // `maskable` for the Android launcher, which crops.
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], $sizes['any']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], $sizes['maskable']);
    }

    public function test_the_manifest_follows_the_shop_name(): void
    {
        Setting::put('store_name', 'متجر الأناقة الحديثة والمجوهرات');
        Setting::put('store_tagline', 'كل ما تحتاجينه');

        $this->artisan('pwa:manifest')->assertSuccessful();

        $written = json_decode(file_get_contents(BuildPwaManifest::path()), true);

        $this->assertSame('متجر الأناقة الحديثة والمجوهرات', $written['name']);
        $this->assertSame('كل ما تحتاجينه', $written['description']);

        // Too long for a home screen, so short_name is cut back to the first
        // word rather than handed to the launcher to truncate mid-letter.
        $this->assertSame('متجر', $written['short_name']);
    }

    public function test_a_short_shop_name_is_kept_whole(): void
    {
        Setting::put('store_name', 'سيمبل');

        $manifest = json_decode(BuildPwaManifest::build(), true);

        $this->assertSame('سيمبل', $manifest['short_name']);
    }

    /* ------------------------------------------------------------ the page -- */

    public function test_the_storefront_links_the_manifest_and_the_ios_meta_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.json', false)
            // Without this one, iOS adds a bookmark that opens in Safari with
            // its address bar — not an app.
            ->assertSee('name="apple-mobile-web-app-capable" content="yes"', false)
            ->assertSee('name="mobile-web-app-capable" content="yes"', false)
            ->assertSee('name="apple-mobile-web-app-title"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            // Safe-area insets are only real once the viewport covers the
            // screen, and installed on a phone there is no browser chrome left
            // to keep the fixed bottom bar off the home indicator.
            ->assertSee('viewport-fit=cover', false);
    }

    public function test_the_install_cards_are_rendered_but_hidden(): void
    {
        $response = $this->get('/');

        // All three ship with the page; resources/js/pwa.js decides which — if
        // any — this device should see. Shipping them hidden is what keeps the
        // Arabic copy in Blade instead of in a JavaScript string.
        foreach (['install', 'ios', 'update'] as $card) {
            $response->assertSee("data-pwa-{$card}-card", false);
        }

        $this->assertSame(
            3,
            substr_count($response->getContent(), 'hidden class='),
            'Every install card must ship hidden, or a desktop visitor meets all three at once.',
        );

        $response->assertSee('إضافة إلى الشاشة الرئيسية')   // the iOS route
            ->assertSee('تثبيت التطبيق')                     // the Android button
            ->assertSee('تحديث الآن');                       // the update action
    }

    /* --------------------------------------------------------- offline page -- */

    public function test_the_offline_page_stands_alone(): void
    {
        $response = $this->get('/offline');

        $response->assertOk()
            ->assertSee('لا يوجد اتصال بالإنترنت')
            ->assertSee(Setting::get('store_name'));

        $html = $response->getContent();

        /*
         * The worker precaches this page onto the device, where it outlives
         * the session that fetched it. So it may carry nothing that expires
         * and nothing about the visitor: a cached CSRF token would be rejected
         * by the next form it was used on, and a cached header would show one
         * customer's name to the next person holding the phone.
         */
        $this->assertStringNotContainsString('csrf-token', $html);
        $this->assertStringNotContainsString('site-header', $html);

        // Nor may it fetch anything to look right — it is shown precisely when
        // fetching is what failed.
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        $this->assertStringContainsString('<style>', $html);
    }

    public function test_the_offline_page_survives_maintenance_mode(): void
    {
        Setting::put('maintenance_mode', '1');

        // The storefront closes...
        $this->get('/')->assertStatus(503);

        // ...but not this page. A worker installed during a maintenance window
        // would otherwise pin "closed for maintenance" onto that device as its
        // permanent offline screen.
        $this->get('/offline')
            ->assertOk()
            ->assertSee('لا يوجد اتصال بالإنترنت');
    }

    /* ------------------------------------------------------ service worker -- */

    public function test_the_service_worker_is_served_from_the_root(): void
    {
        // Scope is read from where the file sits: a worker served from
        // /build/sw.js could only ever control /build.
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_the_service_worker_caches_nothing_private(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        // The allowlist. Only these prefixes may be stored, and every one of
        // them is public, immutable and identical for every visitor.
        $this->assertStringContainsString("const STATIC = ['/build/', '/images/', '/fonts/'];", $worker);

        // /storage is where uploaded product photos and deposit receipts live.
        // A receipt is a customer's bank transfer; it is not ours to keep.
        $this->assertStringContainsString("'/storage/'", $worker);
        $this->assertStringNotContainsString("'/storage/'", $this->allowlistOf($worker));

        // The panel is never handled at all.
        $this->assertStringContainsString("'/admin'", $worker);

        // Non-GET requests go straight to the network, so nothing a cart,
        // login or checkout does can be replayed from a cache.
        $this->assertStringContainsString("request.method !== 'GET'", $worker);
    }

    public function test_the_service_worker_precaches_the_offline_page(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("const OFFLINE_URL = '/offline';", $worker);

        // The route it names has to exist, or every offline navigation gets
        // the browser's error page instead.
        $this->assertSame(url('/offline'), route('offline'));
    }

    /** The source line declaring what may be cached, isolated from the prose around it. */
    private function allowlistOf(string $worker): string
    {
        preg_match('/const STATIC = \[.*?\];/s', $worker, $matches);

        return $matches[0] ?? '';
    }
}
