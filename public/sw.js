/**
 * The storefront's service worker.
 *
 * Registered from resources/js/pwa.js, served from the document root so its
 * scope is the whole origin — which it has to be, because an installed app
 * starts at "/" and must stay inside the app for every link on the site.
 *
 * ── what it will and will not keep ────────────────────────────────────────
 *
 * This shop renders everything on the server. A page carries a CSRF token, the
 * cart count, the signed-in customer's name in the header, and on an account
 * page their orders and addresses. None of that may ever be written to a cache
 * on the device: a stale CSRF token breaks the next form the visitor submits,
 * and a cached account page would be handed to whoever used the phone next.
 *
 * So the rule is an allowlist, not a blocklist — the only responses that reach
 * a cache are the ones named in STATIC below: the build output, the shop's
 * icons, the manifest and the offline page. Those are public, immutable and
 * identical for everybody. Every other request is passed to the network
 * untouched, which includes:
 *
 *   • anything that is not a GET            — carts, logins, checkout, Livewire
 *   • the admin panel under /admin          — never ours to interfere with
 *   • uploaded media under /storage         — unbounded, and user content
 *   • app.js's AJAX fragments and visits    — they are HTML about one visitor
 *   • any cross-origin request              — not ours to decide about
 *
 * Documents are fetched from the network every time. When the network fails,
 * a navigation gets the offline page; nothing else gets a substitute, because
 * a fabricated answer to a cart request is worse than a failed one.
 *
 * ── versioning ────────────────────────────────────────────────────────────
 *
 * Bump VERSION when this file's strategies or its precache list change. The
 * browser byte-compares this script on every navigation, so an edited VERSION
 * is what makes it notice a new worker at all; `activate` then deletes every
 * cache that is not the current version's.
 *
 * Build assets need no bump: Vite writes their hash into the filename, so a
 * rebuilt file is a different URL and the old entries fall away with the
 * version they were stored under.
 */

const VERSION = 'v1';

const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;
const CACHES = [SHELL_CACHE, ASSET_CACHE];

/** The page shown when a navigation cannot reach the server. */
const OFFLINE_URL = '/offline';

/**
 * Everything precached at install, beyond the build output.
 *
 * The icons are here so an installed app has its own mark on the offline page
 * while the network is down, and the manifest so a cold install can still read
 * what the app is called.
 */
const SHELL = [
    OFFLINE_URL,
    '/manifest.json',
    '/favicon.ico',
    '/images/logo-192.png',
    '/images/apple-touch-icon.png',
];

/**
 * The paths whose responses may be stored, as prefixes.
 *
 * /build is Vite's output — hashed filenames, so always safe to keep forever.
 * /images is the shop's generated badge, a handful of small files that never
 * change without a rebuild. Note what is absent: /storage, where uploaded
 * product photos and deposit receipts live. Those are unbounded in number and
 * a receipt is a customer's private document.
 */
const STATIC = ['/build/', '/images/', '/fonts/'];

/** Paths this worker keeps its hands off entirely. */
const OFF_LIMITS = ['/admin', '/livewire/', '/storage/', '/up'];

/* ------------------------------------------------------------------ install --
 * The precache is best-effort per file. One missing icon must not fail the
 * whole install — a worker that never installs leaves the visitor with no
 * offline page at all, which is the one thing this is here to provide.
 */
self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);
            const urls = [...SHELL, ...(await buildAssets())];

            await Promise.all(
                urls.map(async (url) => {
                    try {
                        // `reload` so a precache is never filled from the HTTP
                        // cache — on an update that would store the very files
                        // the new version was meant to replace.
                        const response = await fetch(new Request(url, { cache: 'reload' }));

                        if (response.ok) await cache.put(url, response);
                    } catch {
                        /* offline or missing: the asset is cached on first use instead */
                    }
                }),
            );
        })(),
    );

    // No skipWaiting here on purpose. A new worker waits until the visitor
    // accepts the update in the banner (see resources/js/pwa.js), so a page
    // mid-checkout is never swapped out from under its own JavaScript.
});

/**
 * The current build's entry files, read from the manifest Vite writes.
 *
 * Reading them beats listing them: the filenames carry a content hash that
 * changes on every build, so anything hardcoded here would be wrong by the
 * next deploy. Only the entry CSS and JS are taken — fonts and images that
 * Vite emitted are cached on first use, when the page proves it wants them.
 */
async function buildAssets() {
    try {
        const response = await fetch('/build/manifest.json', { cache: 'reload' });

        if (!response.ok) return [];

        const manifest = await response.json();

        return Object.values(manifest)
            .filter((entry) => entry.isEntry)
            .flatMap((entry) => [entry.file, ...(entry.css ?? [])])
            .map((file) => `/build/${file}`);
    } catch {
        // Running against the Vite dev server: there is no build manifest, and
        // the assets are on a different origin anyway.
        return [];
    }
}

/* ----------------------------------------------------------------- activate --
 * Drop every cache from an older version, then take over the open pages.
 */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();

            await Promise.all(
                names.filter((name) => !CACHES.includes(name)).map((name) => caches.delete(name)),
            );

            // Let the browser start fetching a document while this worker is
            // still waking up; the navigate handler below collects the result.
            if (self.registration.navigationPreload) {
                await self.registration.navigationPreload.enable();
            }

            await self.clients.claim();
        })(),
    );
});

/* -------------------------------------------------------------------- fetch -- */

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Anything that changes state on the server, or asks for a byte range, is
    // the browser's business alone.
    if (request.method !== 'GET' || request.headers.has('range')) return;

    let url;

    try {
        url = new URL(request.url);
    } catch {
        return;
    }

    if (url.origin !== self.location.origin) return;
    if (OFF_LIMITS.some((prefix) => url.pathname.startsWith(prefix))) return;

    if (request.mode === 'navigate') {
        event.respondWith(navigate(event));

        return;
    }

    if (isStatic(url)) {
        event.respondWith(asset(request));
    }

    // Everything else — app.js's page visits and fragments, and any request
    // this worker was not written for — falls through to the network with no
    // service worker in the path at all.
});

const isStatic = (url) =>
    STATIC.some((prefix) => url.pathname.startsWith(prefix)) ||
    url.pathname === '/favicon.ico' ||
    url.pathname === '/manifest.json';

/**
 * A document: always the network, never stored.
 *
 * The response is handed straight back and forgotten, so a signed-in page can
 * never outlive its session. If the network fails the offline page stands in —
 * and if even that is missing (an install that never completed) the failure is
 * rethrown, because the browser's own error page is more honest than a blank
 * response.
 */
async function navigate(event) {
    try {
        const preloaded = await event.preloadResponse;

        return preloaded || (await fetch(event.request));
    } catch (error) {
        const offline = await caches.match(OFFLINE_URL, { cacheName: SHELL_CACHE });

        if (offline) return offline;

        throw error;
    }
}

/**
 * A static asset: the cache first, the network behind it.
 *
 * These filenames carry a content hash or belong to a file that only a rebuild
 * changes, so a hit is never stale and there is nothing to revalidate. A miss
 * is fetched and kept — but only a clean 200 from this origin: an opaque or
 * partial response would poison the cache with something that cannot be
 * inspected or replayed.
 */
async function asset(request) {
    // caches.match searches every cache, which matters: the build's CSS and JS
    // were already precached into the shell at install. Looking only in the
    // runtime cache would miss them, refetch them and keep a second copy of
    // every one — the shop's whole front end, stored twice on the device.
    const hit = await caches.match(request);

    if (hit) return hit;

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        const cache = await caches.open(ASSET_CACHE);

        cache.put(request, response.clone());
    }

    return response;

    // A fetch that throws is left to reject. The browser then treats the asset
    // as it would with no worker at all — a missing stylesheet, a failed image
    // — which is the honest outcome; there is no substitute worth inventing
    // for a file whose whole point was its exact contents.
}

/* ------------------------------------------------------------------ message --
 * The page's half of the update handshake. resources/js/pwa.js sends this when
 * the visitor presses "تحديث الآن" in the update banner, and nothing else ever
 * does — which is why the waiting worker sits still until they do.
 */
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});
