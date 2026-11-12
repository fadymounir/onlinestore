<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Writes public/manifest.json — the file a browser reads to decide the shop
 * can be installed, and what to call it once it is.
 *
 *     php artisan pwa:manifest
 *
 * It is a real file on disk rather than a route for three reasons: a manifest
 * is fetched by the browser without the page's cookies, so it must not depend
 * on a session; it has to stay readable when PHP is down, which is exactly the
 * state an installed app is most likely to meet; and the web server gives a
 * .json file the right content type with nothing asked of it.
 *
 * But the shop's name lives in the `settings` table, so the file cannot simply
 * be hand-written and forgotten — an admin renaming the shop would leave the
 * installed icon labelled with the old name forever. This command is the
 * bridge: it reads the settings and writes the file. The settings page writes
 * the same file through build() after every save, so the two never disagree;
 * this command is how a deploy, or a public/ that was briefly read-only, is
 * caught up by hand. The committed file is the default those settings started
 * from, so a fresh checkout is installable before either runs.
 *
 * The icons are deliberately NOT the uploaded logo. A manifest icon must
 * declare its exact pixel size, and an upload is whatever size the shop gave
 * it; claiming 512x512 for a 300px JPEG makes a blurred home-screen icon on
 * every phone. A shop that wants its own mark installed replaces
 * resources/images/logo-source.jpg and runs `npm run logo`, which is the same
 * answer the tab icon gives in layouts/partials/favicons.blade.php.
 */
class BuildPwaManifest extends Command
{
    protected $signature = 'pwa:manifest';

    protected $description = 'Write public/manifest.json from the shop settings.';

    /**
     * Where the file lands: the document root, so that its scope is "/" and
     * public/sw.js can precache it by the same URL a browser asks for. See
     * config/pwa.php for the one reason that is configurable at all.
     */
    public static function path(): string
    {
        return config('pwa.manifest');
    }

    public function handle(): int
    {
        $path = static::path();

        if (@file_put_contents($path, static::build()) === false) {
            $this->error("تعذّرت الكتابة في {$path}");

            return self::FAILURE;
        }

        $this->info($path.' — '.Setting::get('store_name', config('app.name')));

        return self::SUCCESS;
    }

    /**
     * The manifest, as the JSON that goes in the file.
     *
     * Kept public and static so the settings page can rewrite the file after a
     * save, and so the test can compare what a request serves against what the
     * current settings say it should be.
     */
    public static function build(): string
    {
        $name = (string) Setting::get('store_name', config('app.name'));
        $tagline = (string) Setting::get('store_tagline', '');

        return json_encode([
            'id' => '/',
            'name' => $name,
            // What fits under a home-screen icon: around 12 characters before
            // Android starts truncating, so a long shop name is cut at a word.
            'short_name' => static::shortName($name),
            'description' => $tagline !== '' ? $tagline : $name,
            'lang' => 'ar',
            'dir' => 'rtl',
            // The whole origin. The panel lives under /admin and is inside the
            // scope too — that is deliberate: a narrower scope would hand an
            // admin's navigation back to the browser mid-session.
            'scope' => '/',
            // The query is what tells analytics an installed visit from a
            // browser one; it is inside the scope, so it is a legal start_url.
            'start_url' => '/?source=pwa',
            'display' => 'standalone',
            // Right-to-left shop: the first page should scroll from the start
            // edge like every other page does.
            'orientation' => 'portrait-primary',
            // Matches <meta name="theme-color"> in layouts/shop.blade.php —
            // --color-ink, the header and footer colour.
            'theme_color' => '#1c1917',
            // --color-paper, so the splash screen is the page's own ground and
            // the first paint does not flash white.
            'background_color' => '#faf8f5',
            'categories' => ['shopping'],
            /*
             * Two purposes, four files. `any` is the badge as drawn, which is
             * what a desktop shortcut and a browser tab show uncropped;
             * `maskable` is the same badge inset on paper, because Android
             * crops an installed icon to the launcher's shape and only the
             * central 80% is guaranteed to survive. Declaring both means
             * neither platform has to make do with the other's file.
             */
            'icons' => [
                [
                    'src' => '/images/logo-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/logo.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/icon-maskable-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/images/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            /*
             * Long-press the installed icon and these are the jumps offered.
             * All three are public pages: a shortcut is listed by the launcher
             * whether or not anyone is signed in, so sending one to a guarded
             * route would open on the login screen.
             */
            'shortcuts' => [
                [
                    'name' => 'المتجر',
                    'url' => '/products',
                    'icons' => [['src' => '/images/logo-192.png', 'sizes' => '192x192', 'type' => 'image/png']],
                ],
                [
                    'name' => 'السلة',
                    'url' => '/cart',
                    'icons' => [['src' => '/images/logo-192.png', 'sizes' => '192x192', 'type' => 'image/png']],
                ],
            ],
            'prefer_related_applications' => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
    }

    /**
     * A name short enough to sit under an icon. Cut on a word where that
     * leaves something readable, otherwise cut hard — an ellipsis of our own
     * would only be truncated again by the launcher.
     */
    private static function shortName(string $name): string
    {
        $name = trim($name);

        if (Str::length($name) <= 12) {
            return $name;
        }

        $words = Str::words($name, 1, '');

        return Str::length($words) <= 12 && Str::length($words) > 0
            ? $words
            : Str::substr($name, 0, 12);
    }
}
