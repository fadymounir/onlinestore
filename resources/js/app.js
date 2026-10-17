/**
 * The storefront's AJAX layer.
 *
 * Progressive enhancement, not a rewrite: every control it touches is a plain
 * Blade <form> or <a href> that still works with JavaScript off. This file
 * intercepts them, sends the same request with an `X-Fragment` header, and
 * applies the JSON the server answers with (see App\Http\Controllers\Concerns\SendsFragments):
 *
 *   { message, level, cartCount, swap: { selector: html }, url, inWishlist }
 *
 * Markup contract
 *   data-ajax          on a form  — submit in the background (cart, wishlist, coupon)
 *   data-ajax-nav      on a GET form — fetch, swap, and push the new URL (filters, sort)
 *   data-ajax-auto     on a field  — changing it submits its form, no button press
 *   data-ajax-region   on a container — anchors inside it swap instead of navigating
 *
 * Nothing here recomputes what the server knows. Prices, discounts and stock
 * warnings arrive as rendered HTML; the client only places it.
 */

const JSON_HEADERS = {
    'X-Fragment': '1',
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ------------------------------------------------------------------ loader --
 * One bar at the top of the page, driven by a counter so overlapping requests
 * do not each try to finish it. It also runs for ordinary navigations, which is
 * where a slow page is most visible.
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
 * Replaces the server-rendered <x-alert> for anything that happens over AJAX:
 * the page is not re-rendered, so a flash message has nowhere to land.
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

    if (busy) {
        element.setAttribute('aria-busy', 'true');
        element.disabled = true;
    } else {
        element.removeAttribute('aria-busy');
        element.disabled = false;
    }
}

/** Dim a region while its replacement is in flight. */
function setRegionBusy(selector, busy) {
    document.querySelector(selector)?.classList.toggle('is-swapping', busy);
}

/* ---------------------------------------------------------------- response -- */

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

    if (typeof payload.cartCount === 'number') {
        paintCartCount(payload.cartCount);
    }

    if (payload.url) {
        window.history.pushState({}, '', payload.url);
    }

    // 422 arrives as JSON too: Laravel puts the field errors in `errors`.
    if (payload.errors) {
        toast(Object.values(payload.errors).flat()[0] ?? payload.message, 'error');
    } else {
        toast(payload.message, response.ok ? payload.level ?? 'success' : 'error');
    }

    return true;
}

function paintCartCount(count) {
    const badge = document.querySelector('#cart-count');
    if (!badge) return;

    badge.textContent = count;

    // Not the `hidden` attribute: the badge is display:flex, which beats the
    // user-agent rule for [hidden]. Blade renders the same two classes.
    badge.classList.toggle('hidden', count < 1);
    badge.classList.toggle('flex', count > 0);
}

/** The one place a request is made, so loader and failure handling are uniform. */
async function send(url, options, { control, region } = {}) {
    loader.start();
    setBusy(control, true);
    if (region) setRegionBusy(region, true);

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
        if (region) setRegionBusy(region, false);
    }
}

/* ------------------------------------------------------------ interceptors -- */

/** Background form submit: cart, wishlist, coupons. */
function submitInBackground(form, control) {
    const body = new FormData(form);
    body.set('_token', csrfToken());

    send(form.action, { method: 'POST', body }, { control, region: '#cart-panel' })
        .then(() => flipWishlistHeart(form));
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

/** GET form that filters or sorts a listing: swap the grid, rewrite the URL. */
function navigateInBackground(form, control) {
    const query = new URLSearchParams(new FormData(form));

    // A new filter invalidates the page number; starting from page one is what
    // a shopper expects after narrowing the results.
    query.delete('page');

    // Empty inputs would otherwise ride along as "?min=&max=", which is noise
    // in a URL the customer may copy or bookmark.
    [...query.keys()].forEach((key) => {
        if (query.getAll(key).every((value) => value === '')) query.delete(key);
    });

    const url = `${form.action}?${query}`;

    send(url, { method: 'GET' }, { control, region: '#catalog-results' });
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form');
    if (!form) return;

    // submitter is the button that was pressed, so only it shows as busy.
    const control = event.submitter;

    if (form.matches('[data-ajax]')) {
        event.preventDefault();
        submitInBackground(form, control);
        return;
    }

    if (form.matches('[data-ajax-nav]')) {
        event.preventDefault();
        navigateInBackground(form, control);
        return;
    }

    // A form that really navigates still gets the loader.
    loader.start();
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

/** Anchors inside a swappable region — the pager — swap rather than navigate. */
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;

    // Let the browser handle anything the user asked to open elsewhere.
    if (event.metaKey || event.ctrlKey || event.shiftKey || link.target === '_blank') {
        return;
    }

    const region = link.closest('[data-ajax-region]');
    const url = new URL(link.href, window.location.origin);

    if (region?.id === 'catalog-results' && url.origin === window.location.origin) {
        event.preventDefault();
        send(url.href, { method: 'GET' }, { region: '#catalog-results' }).then(() => {
            document.querySelector('#catalog-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        return;
    }

    if (url.origin === window.location.origin && !link.hasAttribute('download')) {
        loader.start();
    }
});

/** Back and forward across swapped states: re-fetch what that URL shows. */
window.addEventListener('popstate', () => {
    if (!document.querySelector('#catalog-results')) return;

    send(window.location.href, { method: 'GET' }, { region: '#catalog-results' });
});

// A navigation that was cancelled, or a page restored from the back-forward
// cache, leaves the bar mid-flight otherwise.
window.addEventListener('pageshow', () => {
    loader.pending = 0;
    loader.bar?.classList.remove('is-loading');
});

loader.mount();
