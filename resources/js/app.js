/**
 * The storefront's AJAX layer.
 *
 * Progressive enhancement, not a rewrite: every control it touches is a plain
 * Blade <form> or <a href> that still works with JavaScript off. Nothing here
 * recomputes what the server knows — prices, discounts, stock warnings and
 * validation errors all arrive as rendered HTML; the client only places it.
 *
 * There are two kinds of request, and the difference is what moves on screen.
 *
 * 1. A page visit — a click on a link, a search, a form that used to reload
 *    the page. The server renders the very same page it always did, but
 *    through layouts/shop-shell.blade.php, which emits only the regions a
 *    navigation can change:
 *
 *      <div data-page-shell data-title data-chrome data-cart-count>
 *        <div data-shell-region="#site-main"> … </div>
 *
 *    The attribute is a CSS selector; this file replaces that element's
 *    innerHTML. The header, footer, promises strip and <head> stay put, which
 *    is why a cart page costs 6KB over AJAX instead of the 53KB it weighs as
 *    a document.
 *
 * 2. A fragment — a cart or wishlist action, a catalogue filter. The page
 *    identity does not change at all, so the server answers with JSON naming
 *    the one region to patch (see App\Http\Controllers\Concerns\SendsFragments):
 *
 *      { message, level, cartCount, swap: { … }, append: { … }, url, inWishlist }
 *
 *    `swap` replaces a region's contents; `append` adds to the end of one.
 *    A listing loading its next page uses both at once — the cards are added
 *    to the grid, and the link that asked for them is replaced by the next.
 *
 * Markup contract
 *   data-ajax           on a form  — submit in the background (cart, wishlist, coupon)
 *   data-ajax-nav       on a GET form — fetch, swap the grid, push the new URL
 *   data-ajax-auto      on a field  — changing it submits its form, no button press
 *   data-ajax-refresh   on a container — a background action re-renders the page after
 *   data-load-more      on a link  — followed as it nears the screen, result appended
 *   data-no-ajax        on a link or form — opt out, do a real navigation
 *
 * Everything not carrying one of those is a page visit, so a new page is
 * AJAX-navigable with no markup at all: it only has to say
 * `@extends($shopLayout)`.
 */

const JSON_HEADERS = {
    'X-Fragment': '1',
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ------------------------------------------------------------------ loader --
 * One bar at the top of the page, driven by a counter so overlapping requests
 * do not each try to finish it. It also runs for the navigations this file
 * hands back to the browser, which is where a slow page is most visible.
 */
const loader = {
    bar: null,
    pending: 0,

    mount() {
        this.bar = document.createElement('div');
        this.bar.className = 'app-loader';
        this.bar.setAttribute('role', 'progressbar');
        this.bar.setAttribute('aria-label', 'جاري التحميل');
        this.bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(this.bar);
    },

    start() {
        this.pending += 1;

        if (this.pending === 1) {
            this.bar.setAttribute('aria-hidden', 'false');
            // Restart the animation from zero even if it was mid-fade.
            this.bar.classList.remove('is-done');
            this.bar.classList.add('is-loading');
        }
    },

    done() {
        this.pending = Math.max(0, this.pending - 1);

        if (this.pending === 0) {
            this.bar.classList.remove('is-loading');
            this.bar.classList.add('is-done');
            this.bar.setAttribute('aria-hidden', 'true');
        }
    },
};

/* ------------------------------------------------------------------ toasts --
 * Replaces the server-rendered <x-alert> for anything that happens over AJAX
 * without re-rendering the page, so a flash message has nowhere to land. A
 * page visit does re-render <main>, and its alert arrives inside it.
 */
const toastHost = () => {
    let host = document.querySelector('.app-toasts');

    if (!host) {
        host = document.createElement('div');
        host.className = 'app-toasts';
        // polite, not assertive: a cart confirmation should not interrupt a
        // screen reader mid-sentence.
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
    }

    return host;
};

function toast(message, level = 'success') {
    if (!message) return;

    const el = document.createElement('div');
    el.className = `app-toast app-toast--${level === 'error' ? 'error' : 'success'}`;
    el.textContent = message;
    toastHost().appendChild(el);

    // Let the element land before transitioning, or it animates from nothing.
    requestAnimationFrame(() => el.classList.add('is-visible'));

    setTimeout(() => {
        el.classList.remove('is-visible');
        el.addEventListener('transitionend', () => el.remove(), { once: true });
    }, 4000);
}

/* ------------------------------------------------------------- busy states -- */

/** Disable the control that triggered a request, so it cannot double-fire. */
function setBusy(element, busy) {
    if (!element) return;

    element.classList.toggle('is-busy', busy);

    // Only a control with a label has room for a spinner beside it; the
    // wishlist heart is 20px of icon and just dims. See .is-busy--spinner.
    element.classList.toggle('is-busy--spinner', busy && element.textContent.trim() !== '');

    if (busy) {
        element.setAttribute('aria-busy', 'true');
        element.disabled = true;
    } else {
        element.removeAttribute('aria-busy');
        element.disabled = false;
    }
}

/* --------------------------------------------------------------- skeletons --
 * What a region shows while its replacement is in flight.
 *
 * Which placeholder suits a region depends on whether its current content is
 * still worth reading. Every card in the grid is about to change, so skeleton
 * cards stand in for them; the cart's totals still mean something while a
 * quantity saves, so they stay on screen, dimmed under a spinner.
 */
const PLACEHOLDERS = {
    '#catalog-results': 'grid',
    '#cart-panel': 'panel',
    // A navigation: the page being left stays legible under a spinner centred
    // in the viewport rather than in the document, because a shopper halfway
    // down a listing would never see one pinned to the top of <main>.
    '#site-main': 'page',
};

/** The shape of a product card, as a stand-in for one that has not arrived. */
const PENDING_CARD = `
    <div class="app-skeleton__card" aria-hidden="true">
        <div class="app-skeleton__box app-skeleton__media"></div>
        <div class="app-skeleton__box app-skeleton__line app-skeleton__line--brand"></div>
        <div class="app-skeleton__box app-skeleton__line app-skeleton__line--title"></div>
        <div class="app-skeleton__box app-skeleton__line app-skeleton__line--price"></div>
    </div>`;

/** As many placeholder cards as the grid is showing, so the page keeps its height. */
function cardSkeletons(region) {
    // A full page of results on the first visit; after that, whatever is there
    // — the last page of a listing is usually short, and matching it keeps the
    // footer from jumping up the screen and back.
    return PENDING_CARD.repeat(region.querySelectorAll('article').length || 12);
}

/**
 * Placeholder cards pinned to the end of a grid while its next page is in
 * flight. Not the skeleton above: nothing here is being replaced or dimmed —
 * the cards already on screen stay exactly as they are and four more appear
 * below them, which is what is about to happen for real.
 */
const pendingCards = {
    show(selector) {
        const grid = document.querySelector(selector);

        if (!grid || grid.querySelector(':scope > .app-skeleton__card')) return;

        // Four, not twelve: enough to read as "more is coming" and to give the
        // scroll somewhere to go, without throwing the page a screen downwards
        // every time it loads — which would chase the shopper down the page.
        grid.insertAdjacentHTML('beforeend', PENDING_CARD.repeat(4));
    },

    hide(selector) {
        document.querySelectorAll(`${selector} > .app-skeleton__card`).forEach((card) => card.remove());
    },
};

const skeleton = {
    show(selector) {
        const region = document.querySelector(selector);

        // Overlapping requests on one region: the first one's skeleton stands.
        if (!region || region.querySelector(':scope > .app-skeleton')) return;

        const kind = PLACEHOLDERS[selector] ?? 'panel';
        const node = document.createElement('div');

        node.className = `app-skeleton app-skeleton--${kind}`;
        // Decoration only: the progress bar carries role=progressbar, and the
        // toast announces the result, so there is nothing here to read out.
        node.setAttribute('aria-hidden', 'true');
        node.innerHTML = kind === 'grid' ? cardSkeletons(region) : '<div class="app-spinner"></div>';

        region.classList.add('is-swapping', kind === 'grid' ? 'is-swapping--replace' : 'is-swapping--dim');
        region.append(node);
    },

    hide(selector) {
        const region = document.querySelector(selector);
        if (!region) return;

        region.classList.remove('is-swapping', 'is-swapping--replace', 'is-swapping--dim');

        // A successful swap replaced the region's innerHTML and took the
        // skeleton with it; this clears the one left behind by a failure.
        region.querySelector(':scope > .app-skeleton')?.remove();
    },
};

/* ------------------------------------------------- fragments (JSON swaps) -- */

/**
 * Apply a fragment response.
 *
 * Returns false when the answer was not JSON at all — a session timeout
 * redirecting to the login page, say — so the caller can hand over to a real
 * navigation rather than silently swallowing it.
 */
async function applyResponse(response) {
    if (!response.headers.get('content-type')?.includes('application/json')) {
        return false;
    }

    const payload = await response.json();

    Object.entries(payload.swap ?? {}).forEach(([selector, html]) => {
        const target = document.querySelector(selector);
        if (target) target.innerHTML = html;
    });

    // Appends run after swaps: a listing's next page replaces the link it was
    // asked for and adds the cards above it, and doing it the other way round
    // would briefly leave the old link below the new cards.
    Object.entries(payload.append ?? {}).forEach(([selector, html]) => {
        document.querySelector(selector)?.insertAdjacentHTML('beforeend', html);
    });

    if (typeof payload.cartCount === 'number') {
        paintCartCount(payload.cartCount);
    }

    if (payload.url) {
        // Same as a page visit: the entry being left keeps its offset, so
        // backing out of a filtered grid returns to where the shopper was.
        rememberScroll();
        history.pushState(newHistoryState(), '', payload.url);
        shownUrl = payload.url;
    }

    // A swap may have replaced the listing — a filter, a sort, a cleared chip
    // all hand back a fresh #catalog-results — so the link under it is found
    // again. Without this, filtering a listing stopped it loading on scroll.
    initLoadMore();

    // 422 arrives as JSON too: Laravel puts the field errors in `errors`.
    if (payload.errors) {
        toast(Object.values(payload.errors).flat()[0] ?? payload.message, 'error');
    } else {
        toast(payload.message, response.ok ? payload.level ?? 'success' : 'error');
    }

    return true;
}

function paintCartCount(count) {
    if (!Number.isFinite(count)) return;

    // Two badges on a phone — the header's and the bottom bar's — so this
    // matches on the attribute rather than the id.
    document.querySelectorAll('[data-cart-count]').forEach((badge) => {
        badge.textContent = count;

        // Not the `hidden` attribute: the badge is display:flex, which beats
        // the user-agent rule for [hidden]. Blade renders the same two classes.
        badge.classList.toggle('hidden', count < 1);
        badge.classList.toggle('flex', count > 0);
    });
}

/** The one place a fragment request is made, so loader and failure handling are uniform. */
async function send(url, options, { control, region } = {}) {
    loader.start();
    setBusy(control, true);
    if (region) skeleton.show(region);

    // Any mutation can change what a cached page would say — a cart badge, a
    // wishlist heart, an address list. Cheaper to re-fetch than to reason about,
    // and a remembered grid is no different: it is markup of the same age.
    if ((options.method ?? 'GET') !== 'GET') forgetPages();

    try {
        const response = await fetch(url, {
            ...options,
            headers: { ...JSON_HEADERS, ...(options.headers ?? {}) },
            credentials: 'same-origin',
        });

        // A stale CSRF token cannot be recovered from in the background.
        if (response.status === 419) {
            window.location.reload();
            return;
        }

        if (!(await applyResponse(response))) {
            window.location.href = response.url || url;
        }
    } catch {
        // Offline, or the request was cut off. The forms still work, so say so
        // rather than leaving the click looking ignored.
        toast('تعذّر الاتصال بالخدمة. حاول مرة أخرى.', 'error');
    } finally {
        loader.done();
        setBusy(control, false);
        if (region) skeleton.hide(region);
    }
}

/* ----------------------------------------------------------- page visits --
 * The navigation layer: what used to be a browser page load.
 */

/** What the chrome on screen was rendered for; the server skips it if unchanged. */
const chromeToken = () => document.body.dataset.chrome ?? 'guest';

/** Whether the phone's category rail is currently mounted — it is all or nothing. */
const railToken = () => (document.querySelector('#site-rail')?.firstElementChild ? '1' : '0');

function pageHeaders(extra = {}) {
    return {
        'X-Page-Visit': '1',
        'X-Page-Chrome': chromeToken(),
        'X-Page-Rail': railToken(),
        ...extra,
    };
}

/* ---- the prefetch cache ----------------------------------------------------
 * A hovered link is usually about to be clicked, and fetching it then makes
 * the click itself free. Kept deliberately short-lived and thrown away whole
 * on any mutation: a cart page from thirty seconds ago is not worth showing.
 */
const PAGE_CACHE_TTL = 20_000;
const PAGE_CACHE_MAX = 12;
const pageCache = new Map();

/*
 * The key carries the chrome and rail state the response was fetched under,
 * because the server omits regions it believes are already correct. A reply
 * fetched as a guest is missing the header a signed-in visitor now needs, so
 * it must miss the cache rather than paint a stale one.
 */
const cacheKey = (url) => `${url}|${chromeToken()}|${railToken()}`;

/** Everything held about pages as they were. Thrown away together. */
function forgetPages() {
    pageCache.clear();
    listings.clear();
    grownListings.clear();
}

function cacheRead(url) {
    const hit = pageCache.get(cacheKey(url));

    if (!hit) return null;

    if (Date.now() - hit.at > PAGE_CACHE_TTL) {
        pageCache.delete(cacheKey(url));
        return null;
    }

    return hit;
}

function cacheWrite(url, entry) {
    // Oldest out first: insertion order is Map's iteration order.
    if (pageCache.size >= PAGE_CACHE_MAX) {
        pageCache.delete(pageCache.keys().next().value);
    }

    pageCache.set(cacheKey(url), { ...entry, at: Date.now() });
}

/* ---- history and scroll ----------------------------------------------------
 * With scroll restoration left on `auto` the browser would restore the old
 * offset against content that has not arrived yet, so this file owns it. Each
 * history entry gets an id, and the offset it was left at is remembered
 * against that id — which is what makes going back to a product grid land
 * where the shopper was rather than at the top.
 */
const SCROLL_STORE = 'app:scroll';

let historySerial = 0;

const scrollMemory = new Map(readScrollMemory());

function readScrollMemory() {
    try {
        return JSON.parse(sessionStorage.getItem(SCROLL_STORE) ?? '[]');
    } catch {
        // Private mode, or storage the browser refuses to hand over. Back and
        // forward still work within this document; only offsets carried across
        // a reload are lost.
        return [];
    }
}

function newHistoryState() {
    historySerial += 1;

    return { page: historySerial };
}

/** Remember where the entry we are leaving was scrolled to. */
function rememberScroll() {
    const id = history.state?.page;

    if (id) scrollMemory.set(id, window.scrollY);
}

/* ---- the announcer --------------------------------------------------------
 * A page visit changes the document without the browser announcing anything,
 * so the new title is read out here. Off-screen, polite, and created once.
 */
function announce(title) {
    let region = document.querySelector('[data-page-announcer]');

    if (!region) {
        region = document.createElement('p');
        region.setAttribute('data-page-announcer', '');
        region.setAttribute('aria-live', 'polite');
        region.className = 'sr-only';
        document.body.appendChild(region);
    }

    region.textContent = title;
}

/* ---- applying a shell ----------------------------------------------------- */

const parser = new DOMParser();

/** Pull the shell out of a response body, or null if this was not a storefront page. */
function parseShell(html) {
    return parser.parseFromString(html, 'text/html').querySelector('[data-page-shell]');
}

/**
 * Put a parsed shell on screen.
 *
 * Only the regions the server chose to send are touched — a navigation that
 * changed neither the signed-in visitor nor the rail's visibility arrives
 * carrying <main> and the bottom bar and nothing else.
 */
function applyShell(shell, url) {
    shell.querySelectorAll('[data-shell-region]').forEach((region) => {
        const target = document.querySelector(region.dataset.shellRegion);

        if (target) target.innerHTML = region.innerHTML;
    });

    if (shell.dataset.title) {
        document.title = shell.dataset.title;
        announce(shell.dataset.title);
    }

    const description = document.querySelector('meta[name="description"]');

    if (description && shell.dataset.description !== undefined) {
        description.content = shell.dataset.description;
    }

    // Signing in or out: remember who the furniture now belongs to, so the
    // next visit stops asking for it.
    if (shell.dataset.chrome) {
        document.body.dataset.chrome = shell.dataset.chrome;
    }

    paintCartCount(Number(shell.dataset.cartCount));

    shownUrl = url;

    // Before initPage, so the observers bind to the restored grid's link
    // rather than to the first page's, which is about to be thrown away.
    restoreListing(url);

    initPage();
    fadeInMain();
}

/**
 * The arriving page fades up over its own first frames — a swap with no
 * transition at all reads as a glitch rather than a navigation. Only the new
 * content animates: nothing fades *out*, because that would put an animation's
 * length between the click and the page, which is the opposite of the point.
 */
function fadeInMain() {
    const main = document.querySelector('#site-main');
    if (!main) return;

    main.classList.remove('is-page-in');
    // Reading a layout property flushes the removal, so re-adding the class
    // restarts the animation instead of being coalesced into a no-op.
    void main.offsetWidth;
    main.classList.add('is-page-in');
}

/** Where a visit leaves the viewport. */
function applyScroll(scroll, url) {
    if (scroll === 'keep') return;

    if (typeof scroll === 'number') {
        window.scrollTo(0, scroll);
        return;
    }

    // A link to an anchor on the new page: honour it the way the browser would.
    // By id rather than as a selector — "#2024-sale" is a legal id and an
    // illegal selector, and querySelector would throw on it.
    const hash = new URL(url, window.location.origin).hash;
    const anchor = hash.length > 1 ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;

    if (anchor) {
        anchor.scrollIntoView();
        return;
    }

    // Instantly, not smoothly: a smooth scroll on a navigation means watching
    // the old page slide past before reading the new one.
    window.scrollTo(0, 0);
}

/** Long enough that a cached visit never flashes a loader nobody needed. */
const VISIT_LOADER_DELAY = 200;

/*
 * One visit at a time. A shopper who taps three categories in a second should
 * land on the third, and without this the slowest reply would win.
 */
let inFlight = null;

/**
 * Navigate without a page load.
 *
 * @param {string} target
 * @param {object} options
 * @param {string} [options.method]   POST for a form; GET otherwise
 * @param {FormData} [options.body]
 * @param {Element} [options.control] the control to mark busy
 * @param {boolean} [options.push]    push a history entry (false on back/forward)
 * @param {'top'|'keep'|number} [options.scroll]
 */
async function visit(target, { method = 'GET', body = null, control = null, push = true, scroll = 'top' } = {}) {
    const url = new URL(target, window.location.href).href;
    const isRead = method === 'GET';

    inFlight?.abort();
    inFlight = new AbortController();

    const { signal } = inFlight;

    // A mutation invalidates every cached page, including this URL's own.
    if (!isRead) forgetPages();

    const cached = isRead ? cacheRead(url) : null;

    loader.start();
    setBusy(control, true);
    document.documentElement.classList.add('is-visiting');

    /*
     * The page-level loader, held back a fifth of a second.
     *
     * Most visits are already in hand — prefetched on hover, or a few hundred
     * bytes over a warm connection — and painting a spinner for forty
     * milliseconds before the page lands reads as a flicker, not as progress.
     * So the thin bar at the top covers the fast ones and this covers the rest.
     */
    const showLoader = setTimeout(() => skeleton.show('#site-main'), VISIT_LOADER_DELAY);

    try {
        let html;
        let finalUrl;
        let status = 200;

        if (cached) {
            ({ html, url: finalUrl } = cached);
        } else {
            const response = await fetch(url, {
                method,
                body,
                headers: pageHeaders(),
                credentials: 'same-origin',
                signal,
            });

            // A stale CSRF token cannot be recovered from in the background.
            if (response.status === 419) {
                window.location.reload();
                return;
            }

            html = await response.text();
            // Not `url`: a POST answers with a redirect, and where it landed is
            // the address that belongs in the bar.
            finalUrl = response.url || url;
            status = response.status;
        }

        const shell = parseShell(html);

        /*
         * No shell means this was not a storefront page rendered through the
         * shop layout — a 404, the maintenance screen, the admin panel, an
         * expired session landing on a login outside the storefront. Hand it
         * to the browser rather than guessing.
         */
        if (!shell) {
            window.location.href = finalUrl;
            return;
        }

        if (isRead && status === 200 && !cached) {
            cacheWrite(url, { html, url: finalUrl });
        }

        // Both of these describe the page being left, so they have to happen
        // before its content — and, for the offset, its history entry — moves.
        rememberListing();

        if (push) {
            rememberScroll();
            history.pushState(newHistoryState(), '', finalUrl);
        }

        applyShell(shell, finalUrl);
        applyScroll(scroll, finalUrl);

        /*
         * Focus moves into the new page, which is what a real navigation does
         * for free. Without it a keyboard user's next Tab resumes inside the
         * header they had already walked past.
         */
        document.querySelector('#site-main')?.focus({ preventScroll: true });
    } catch (error) {
        // An aborted visit was replaced by a newer one; its loader is closed in
        // `finally` and the newer visit owns the screen.
        if (error.name === 'AbortError') return;

        toast('تعذّر تحميل الصفحة. حاول مرة أخرى.', 'error');
    } finally {
        clearTimeout(showLoader);
        skeleton.hide('#site-main');
        loader.done();
        setBusy(control, false);
        document.documentElement.classList.remove('is-visiting');
    }
}

/* ---- prefetch on intent ---------------------------------------------------
 * The cheapest speed there is: by the time a hover becomes a click the page
 * is usually already in hand. Only ever GET, only same-origin, and marked
 * `Purpose: prefetch` so Laravel does not record it as the page `back()`
 * should return to.
 */
const PREFETCH_DELAY = 80;

let prefetchTimer = null;

function prefetch(url) {
    if (cacheRead(url) || navigator.connection?.saveData) return;

    fetch(url, {
        headers: pageHeaders({ Purpose: 'prefetch' }),
        credentials: 'same-origin',
        priority: 'low',
    })
        .then((response) => (response.ok ? response.text().then((html) => ({ html, url: response.url || url })) : null))
        .then((entry) => {
            // Only a real storefront page is worth keeping; anything else would
            // be re-fetched on the click anyway. A string test, not a parse:
            // this runs on hover, and parsing a page nobody has asked for yet
            // is work done on the off-chance.
            if (entry?.html.includes('data-page-shell')) cacheWrite(url, entry);
        })
        .catch(() => {
            // A prefetch that fails costs nothing: the click will fetch again.
        });
}

/* ------------------------------------------------------- endless listings --
 * Product listings have no pager. The link under the grid is followed as it
 * comes within a screen of the viewport, and the twelve cards it answers with
 * are added to the grid that is already there.
 *
 * The URL is deliberately left alone. It names the listing — these filters,
 * this category — not how far down it the shopper has read, so it stays
 * shareable and one Back still leaves the page instead of unwinding every
 * page that was loaded into it. What going back *to* a listing restores is
 * handled by the listing memory below, which puts the whole grown grid back.
 */

/** How early to start: a screen's worth, so the cards are there on arrival. */
const LOAD_MORE_MARGIN = '0px 0px 600px 0px';

function loadMore(link) {
    // An observer that fires twice before the first reply lands, a click on a
    // link the observer is already following — both end up here.
    if (link.dataset.loading) return;

    link.dataset.loading = '1';

    // The grid the cards are headed for, so the wait is shown where the
    // result will appear rather than only as a spinner on the link.
    const grid = link.dataset.loadMore;

    pendingCards.show(grid);

    send(
        link.href,
        { method: 'GET', headers: { 'X-Fragment-Mode': 'append' } },
        { control: link },
    ).then(() => {
        pendingCards.hide(grid);

        // This listing is now longer than the server will send it as a first
        // page, so it is worth putting back when the shopper returns to it.
        grownListings.add(shownUrl);

        // The reply replaced this link with the next one — or with nothing, at
        // the end of the listing. Either way the element just observed is gone.
        delete link.dataset.loading;
        initLoadMore();
    });
}

/*
 * Held on its own rather than in the page's observer list, because this one is
 * re-bound on its own schedule: a filter swaps the whole listing out without a
 * page visit, and the link that comes back needs watching even though nothing
 * else on the page has changed.
 */
let loadMoreWatcher = null;

/** Watch the current load-more link, whatever happened to the last one. */
function initLoadMore() {
    // Idempotent: calling this twice must not leave two observers racing to
    // follow the same link.
    loadMoreWatcher?.disconnect();
    loadMoreWatcher = null;

    const link = document.querySelector('[data-load-more]');

    if (!link || !('IntersectionObserver' in window)) return;

    loadMoreWatcher = new IntersectionObserver(
        ([entry]) => {
            if (!entry.isIntersecting) return;

            // One shot: the next page brings its own link and its own observer.
            loadMoreWatcher.disconnect();
            loadMore(link);
        },
        { rootMargin: LOAD_MORE_MARGIN },
    );

    loadMoreWatcher.observe(link);
}

/* ----------------------------------------------------------- what Back sees --
 * A shopper who scrolled through sixty products, opened one, and pressed Back
 * should find sixty products and the place they left off — not the first
 * twelve and a scroll offset that no longer means anything.
 *
 * So the grown listing is kept as it stood, under the URL it was grown from,
 * and put back when that URL is rendered again. It is the markup the server
 * sent, minutes ago at most, and it is thrown away on any mutation — the same
 * rule the prefetch cache lives by, for the same reason.
 */
const LISTING_TTL = 5 * 60 * 1000;
const LISTING_MAX = 3;

const listings = new Map();

/*
 * Which listings have been scrolled past their first page.
 *
 * Kept here rather than read off the page, because the two obvious DOM tells
 * both lie. A load-more link is absent at the end of a listing — exactly the
 * case where the most was loaded and the most would be lost. And the region's
 * own element does not survive the shell swap that restores it, so a mark on
 * it would be gone the second time round.
 */
const grownListings = new Set();

/**
 * The URL the document currently shows.
 *
 * Not window.location: on a back or forward that has already changed by the
 * time this file hears about it, so the listing being left would be filed
 * under the page being arrived at.
 */
let shownUrl = window.location.href;

/** The region whose growth is worth remembering, if this page has one. */
const listingRegion = () => document.querySelector('#catalog-results, [data-ajax-refresh]');

function rememberListing() {
    // Nothing to put back until the shopper has actually scrolled for it: the
    // server's own first page is fresher than anything kept here.
    if (!grownListings.has(shownUrl)) return;

    const region = listingRegion();

    if (!region) return;

    if (listings.size >= LISTING_MAX) listings.delete(listings.keys().next().value);

    listings.set(shownUrl, { html: region.innerHTML, at: Date.now() });
}

function restoreListing(url) {
    const remembered = listings.get(url);

    if (!remembered) return;

    if (Date.now() - remembered.at > LISTING_TTL) {
        listings.delete(url);
        return;
    }

    const region = listingRegion();

    // Only over a listing that came back as its own first page. Anything else
    // means the server is now answering this URL with something different.
    if (region?.querySelector('[data-load-more], #catalog-grid, #wishlist-grid')) {
        region.innerHTML = remembered.html;

        // Still grown, and the element carrying it is new — so say so again,
        // or leaving a second time would keep nothing.
        grownListings.add(url);
    }
}

/* ------------------------------------------------------------ interceptors -- */

/** Is this a link this file should take over? */
function isVisitable(link) {
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-ajax')) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.getAttribute('rel')?.includes('external')) return false;

    const url = new URL(link.href, window.location.origin);

    // mailto:, tel:, whatsapp — and any other origin.
    if (url.origin !== window.location.origin) return false;

    // A same-page anchor is the browser's job, not a navigation.
    return !(url.pathname === window.location.pathname && url.search === window.location.search && url.hash);
}

/** Background form submit: cart, wishlist, coupons. */
function submitInBackground(form, control) {
    const body = new FormData(form);
    body.set('_token', csrfToken());

    send(form.action, { method: 'POST', body }, { control, region: '#cart-panel' }).then(() => {
        flipWishlistHeart(form);

        /*
         * A page whose own list is what the action changed — the wishlist,
         * where removing a product should take its card away. The server has
         * already applied it; this re-renders the page quietly to show it.
         */
        if (document.querySelector('[data-ajax-refresh]')) {
            visit(window.location.href, { push: false, scroll: 'keep' });
        }
    });
}

/**
 * The heart is the only control whose own appearance is the feedback, and the
 * server tells us which way it went — the card does not know its own state.
 */
function flipWishlistHeart(form) {
    if (!form.matches('[data-ajax-wishlist]')) return;

    const button = form.querySelector('button');
    button?.classList.toggle('is-active');
}

/** The query a GET form describes, with the noise left out. */
function formQuery(form) {
    const query = new URLSearchParams(new FormData(form));

    // A new filter invalidates the page number; starting from page one is what
    // a shopper expects after narrowing the results.
    query.delete('page');

    // Empty inputs would otherwise ride along as "?min=&max=", which is noise
    // in a URL the customer may copy or bookmark.
    [...query.keys()].forEach((key) => {
        if (query.getAll(key).every((value) => value === '')) query.delete(key);
    });

    return query;
}

/** GET form that filters or sorts a listing: swap the grid, rewrite the URL. */
function navigateInBackground(form, control) {
    send(`${form.action}?${formQuery(form)}`, { method: 'GET' }, { control, region: '#catalog-results' });
}

/** Every other form: the header search, login, checkout, addresses, reviews. */
function visitForm(form, control) {
    if ((form.getAttribute('method') ?? 'GET').toUpperCase() === 'GET') {
        const query = formQuery(form);

        visit(query.toString() ? `${form.action}?${query}` : form.action, { control });
        return;
    }

    // The submitter's own name/value belongs in the payload — it is how a form
    // with two buttons says which one was pressed.
    let body;

    try {
        body = new FormData(form, control);
    } catch {
        body = new FormData(form);
    }

    // Laravel's @csrf token was rendered with the page; the one in <head> is
    // the one this session currently holds.
    body.set('_token', csrfToken());

    visit(form.action, { method: 'POST', body, control });
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form');

    if (!form || form.hasAttribute('data-no-ajax') || (form.target && form.target !== '_self')) return;

    // submitter is the button that was pressed, so only it shows as busy.
    const control = event.submitter;

    event.preventDefault();

    if (form.matches('[data-ajax]')) {
        submitInBackground(form, control);
        return;
    }

    if (form.matches('[data-ajax-nav]')) {
        navigateInBackground(form, control);
        return;
    }

    visitForm(form, control);
});

/** Anything marked data-ajax-auto submits its form as soon as it changes. */
document.addEventListener('change', (event) => {
    const marked = event.target.closest('[data-ajax-auto]');
    if (!marked) return;

    // The marker sits either on one field (a sort select, a brand checkbox) or
    // on a whole form whose every field should submit it (a cart row's
    // quantity). closest() finds either, so resolve the form from both.
    const form = marked instanceof HTMLFormElement ? marked : marked.form;

    // requestSubmit fires the submit event above, so one code path handles both.
    form?.requestSubmit();
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;

    // Let the browser handle anything the user asked to open elsewhere.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

    // The product gallery handles its own clicks below and never navigates.
    if (link.closest('[data-gallery]')) return;

    if (!isVisitable(link)) return;

    event.preventDefault();

    // "عرض المزيد" is the one link that adds to the page instead of replacing
    // it. Usually the observer below has already followed it; this is for the
    // shopper who tabbed to it, or whose browser has no observer.
    if (link.matches('[data-load-more]')) {
        loadMore(link);
        return;
    }

    visit(link.href);
});

/* Hover and touch are both "about to click". */
document.addEventListener('mouseover', (event) => {
    const link = event.target.closest('a[href]');

    clearTimeout(prefetchTimer);

    // Everything but the load-more link, which is not a page and is about to
    // be followed by the observer anyway. Product cards very much included:
    // hovering one in a grid is the clearest signal there is.
    if (!link || !isVisitable(link) || link.matches('[data-load-more]')) return;

    prefetchTimer = setTimeout(() => prefetch(link.href), PREFETCH_DELAY);
});

document.addEventListener(
    'touchstart',
    (event) => {
        const link = event.target.closest('a[href]');

        if (link && isVisitable(link) && !link.matches('[data-load-more]')) prefetch(link.href);
    },
    { passive: true },
);

/** Back and forward: re-fetch what that URL shows and restore where it was. */
window.addEventListener('popstate', (event) => {
    visit(window.location.href, {
        push: false,
        scroll: scrollMemory.get(event.state?.page) ?? 0,
    });
});

// A navigation that was cancelled, or a page restored from the back-forward
// cache, leaves the bar mid-flight otherwise.
window.addEventListener('pageshow', () => {
    loader.pending = 0;
    loader.bar?.classList.remove('is-loading');
});

/*
 * Offsets outlive the document, so a reload — or a back into a page that was
 * reloaded since — still lands where the shopper was.
 */
window.addEventListener('pagehide', () => {
    rememberScroll();

    try {
        sessionStorage.setItem(SCROLL_STORE, JSON.stringify([...scrollMemory]));
    } catch {
        // Nothing to do: the offsets simply do not survive this reload.
    }
});

/*
 * The first entry needs an id like any other, or the offset of the page the
 * visitor arrived on has nowhere to be recorded. On a reload the entry already
 * carries one, and the offset it kept is restored.
 */
if (history.state?.page) {
    historySerial = history.state.page;

    const resume = scrollMemory.get(history.state.page);

    if (resume) window.addEventListener('load', () => window.scrollTo(0, resume), { once: true });
} else {
    history.replaceState(newHistoryState(), '');
}

history.scrollRestoration = 'manual';

loader.mount();

/* ------------------------------------------------------------------ chrome --
 * Page furniture that is bound once, because the elements it watches are never
 * replaced: <main> changes, the header around it does not.
 */

/** The sticky header grows a shadow once the page has scrolled under it. */
const header = document.querySelector('.site-header');

if (header) {
    const paintHeader = () => header.classList.toggle('is-stuck', window.scrollY > 8);

    paintHeader();
    window.addEventListener('scroll', paintHeader, { passive: true });
}

/**
 * Product gallery. Each thumbnail is a link to the full-size file — that is
 * what a no-JS visitor gets — and here it becomes an in-place swap of the
 * main image instead. Delegated, so it survives every page visit.
 */
document.addEventListener('click', (event) => {
    const thumb = event.target.closest('[data-gallery] a[href]');
    if (!thumb) return;

    if (event.metaKey || event.ctrlKey || event.shiftKey) return;

    const gallery = thumb.closest('[data-gallery]');
    const main = gallery.querySelector('[data-gallery-main]');
    if (!main) return;

    event.preventDefault();

    main.src = thumb.getAttribute('href');
    main.alt = thumb.querySelector('img')?.alt ?? main.alt;

    gallery.querySelectorAll('a[href]').forEach((other) => {
        other.removeAttribute('aria-current');
    });

    thumb.setAttribute('aria-current', 'true');
});

/**
 * The search box lives in the header, which a page visit does not replace — so
 * after a search, or after leaving one, the field is squared up with the URL
 * by hand. Otherwise a term stays in the box on a page that is not showing it.
 */
function syncSearchFields() {
    const term = new URL(window.location.href).searchParams.get('q') ?? '';

    document.querySelectorAll('#site-header input[name="q"]').forEach((input) => {
        // Never while it is being typed in: that would fight the customer.
        if (input !== document.activeElement) input.value = term;
    });
}

/* --------------------------------------------------------------- page init --
 * Everything below watches elements that <main> or the bottom bar owns, so it
 * is re-bound after every page visit. Observers are disconnected first: the
 * nodes they were watching are gone, and a second observer on the new ones
 * would fire twice.
 */
const observers = [];

function initPage() {
    while (observers.length) observers.pop().disconnect();

    syncSearchFields();
    initLoadMore();
    initReveal();
    initBuyBar();
    initDepositPanel();
    initReceiptPreviews();
}

/**
 * Sections fade up as they reach the viewport. `.reveal` is what hides them,
 * and `js-reveal` is only set here — a section whose observer never runs would
 * otherwise sit at opacity 0 forever, which is also what a browser without
 * IntersectionObserver and `prefers-reduced-motion` get.
 */
function initReveal() {
    const sections = document.querySelectorAll('.reveal:not(.is-revealed)');

    if (!('IntersectionObserver' in window) || !sections.length) return;

    document.documentElement.classList.add('js-reveal');

    const watcher = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                entry.target.classList.add('is-revealed');
                watcher.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -10% 0px' },
    );

    sections.forEach((section) => watcher.observe(section));
    observers.push(watcher);
}

/**
 * The product page's fixed buy bar on a phone: shown only while the real
 * add-to-cart form is off screen, so the two are never on screen together.
 */
function initBuyBar() {
    const buyBar = document.querySelector('[data-buybar]');
    const anchor = document.querySelector('[data-buybar-anchor]');

    if (!buyBar || !anchor || !('IntersectionObserver' in window)) return;

    document.documentElement.classList.add('js-buybar');

    const watcher = new IntersectionObserver(
        ([entry]) => buyBar.classList.toggle('is-shown', !entry.isIntersecting),
        { threshold: 0 },
    );

    watcher.observe(anchor);
    observers.push(watcher);
}

/**
 * Checkout's deposit panel. The customer types what they actually transferred
 * — the minimum share, the whole order, or anything in between — and this
 * keeps the "left to pay on delivery" line honest as they type. With
 * JavaScript off the line still shows the figure the page was rendered with,
 * and the server re-checks the range either way.
 */
function initDepositPanel() {
    const panel = document.querySelector('[data-deposit]');
    if (!panel) return;

    const amount = panel.querySelector('[data-deposit-amount]');
    const remaining = panel.querySelector('[data-deposit-remaining] span');
    const total = Number.parseFloat(panel.dataset.depositTotal);

    if (amount && remaining && Number.isFinite(total)) {
        const money = (value) =>
            `${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ج.م`;

        amount.addEventListener('input', () => {
            const paid = Number.parseFloat(amount.value);
            const left = Number.isFinite(paid) ? total - paid : total;

            remaining.textContent = money(Math.min(Math.max(left, 0), total));
        });
    }

    /*
     * The amount and the receipt are required — but only while the deposit is
     * the chosen method. The panel is hidden with `display: none` for cash on
     * delivery, and a required field inside a hidden block stops the browser
     * submitting the form at all, so the attribute follows the radio. The
     * server asks for both regardless of what the markup says.
     */
    const radios = document.querySelectorAll('input[name="payment_method"]');
    const conditional = panel.querySelectorAll('[data-deposit-required]');

    const syncRequired = () => {
        const chosen = document.querySelector('input[name="payment_method"]:checked');
        const wanted = chosen?.value === 'deposit';

        conditional.forEach((field) => {
            field.required = wanted;
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', syncRequired));
    syncRequired();
}

/**
 * Receipt preview. The admin approves the deposit from this file, so the
 * customer sees exactly what they are about to send — the image itself, or a
 * named link for a PDF — before the form goes anywhere.
 */
function initReceiptPreviews() {
    document.querySelectorAll('[data-receipt-input]').forEach((input) => {
        const scope = input.closest('[data-receipt-scope]');
        const figure = scope?.querySelector('[data-receipt-preview]');

        if (!figure) return;

        const image = figure.querySelector('[data-receipt-image]');
        const link = figure.querySelector('[data-receipt-file]');
        const caption = figure.querySelector('[data-receipt-caption]');
        const limit = 5 * 1024 * 1024;

        let objectUrl = null;

        const release = () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        };

        const size = (bytes) =>
            bytes >= 1024 * 1024
                ? `${(bytes / 1024 / 1024).toFixed(2)} ميجابايت`
                : `${Math.max(1, Math.round(bytes / 1024))} كيلوبايت`;

        input.addEventListener('change', () => {
            release();

            const file = input.files?.[0];

            if (!file) {
                figure.hidden = true;
                return;
            }

            objectUrl = URL.createObjectURL(file);

            const isImage = file.type.startsWith('image/');

            image.hidden = !isImage;
            link.hidden = isImage;

            if (isImage) {
                image.src = objectUrl;
            } else {
                link.href = objectUrl;
                link.textContent = `فتح ${file.name}`;
            }

            caption.textContent = `${file.name} · ${size(file.size)}`;
            caption.classList.toggle('text-red-700', file.size > limit);

            if (file.size > limit) {
                caption.textContent += ' — أكبر من الحد المسموح (5 ميجابايت)';
            }

            figure.hidden = false;
        });

        window.addEventListener('pagehide', release);
    });
}

initPage();

/**
 * The first-paint loader. It is on screen before this file runs (the `js`
 * class in <head> reveals it, a quarter-second late so fast loads never flash
 * it), and this is what takes it away: on `load`, on a page restored from the
 * back-forward cache, and on a 4s timer — a slow image must never leave a
 * shopper staring at a logo. It never returns: from here on a navigation is
 * the progress bar at the top of the page.
 */
const preloader = document.querySelector('.app-preloader');

if (preloader) {
    const dismissPreloader = () => preloader.classList.add('is-done');

    if (document.readyState === 'complete') {
        dismissPreloader();
    } else {
        window.addEventListener('load', dismissPreloader, { once: true });
        setTimeout(dismissPreloader, 4000);
    }

    window.addEventListener('pageshow', dismissPreloader);
}
