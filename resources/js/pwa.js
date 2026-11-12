/**
 * The storefront's PWA layer: registering the service worker, offering the
 * install, and handing over to a new version when one arrives.
 *
 * A separate Vite entry from app.js, and deliberately so. app.js is the AJAX
 * layer — it owns what happens inside the page. Nothing here touches the page
 * at all: it reveals cards that resources/views/components/install-app.blade.php
 * already rendered, and it talks to a worker. Keeping the two apart means the
 * shop still works exactly as it did with this file deleted.
 *
 * It builds no markup. Every string a visitor reads is Arabic written in Blade
 * with the rest of the shop's words; this file only decides which of the three
 * cards, if any, applies to the device in front of it.
 *
 * ── the three cards ───────────────────────────────────────────────────────
 *
 * Android and the Chromium desktops announce themselves: the browser fires
 * `beforeinstallprompt` when it is willing to install, and that event is the
 * only way to open the prompt later. So the install card is shown in response
 * to the event and never otherwise — a button that cannot install anything is
 * worse than no button.
 *
 * iOS fires nothing, and Safari has no API for this at all. The only route is
 * the visitor's own hands through the Share menu, so the iOS card explains it
 * rather than offering a control.
 *
 * The update card is shown when a second worker has installed behind the one
 * running the page, and is waiting for permission to take over.
 */

/* --------------------------------------------------------------- the cards -- */

const card = (name) => document.querySelector(`[data-pwa-${name}-card]`);

/**
 * Show one card, and only one.
 *
 * All three sit in the same place — the floating slot above the phone's bottom
 * bar — so two on screen at once would be two cards stacked on each other. The
 * one being shown wins: an update that arrives while the install offer is up
 * is the more urgent of the two, and an install that completes takes its own
 * card away anyway.
 */
const show = (name) => {
    ['install', 'ios', 'update'].forEach((other) => {
        if (other !== name) hide(other);
    });

    const el = card(name);

    if (el) el.hidden = false;
};

const hide = (name) => {
    const el = card(name);

    if (el) el.hidden = true;
};

/* ---------------------------------------------------------------- dismissal --
 * A dismissed card stays away for a month rather than forever. Forever is the
 * wrong answer twice over: someone who tapped the × on a borrowed phone is not
 * saying "never", and a shop that has become a weekly habit is worth offering
 * again. A month is long enough that nobody feels nagged.
 *
 * localStorage can throw outright — Safari in private browsing historically
 * did, and a browser set to block site data still does — so every read and
 * write is wrapped. A failure means the card is offered again next visit,
 * which is the harmless direction to fail in.
 */
const DISMISS_DAYS = 30;

const dismissKey = (name) => `pwa:dismissed:${name}`;

function dismissed(name) {
    try {
        const until = Number(localStorage.getItem(dismissKey(name)));

        return Number.isFinite(until) && until > Date.now();
    } catch {
        return false;
    }
}

function remember(name) {
    try {
        localStorage.setItem(dismissKey(name), String(Date.now() + DISMISS_DAYS * 864e5));
    } catch {
        /* nothing to do: the card simply reappears next time */
    }
}

// One listener for all three × buttons. Delegated from the document so it
// does not care when the cards were rendered.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-pwa-dismiss]');

    if (!button) return;

    const name = button.dataset.pwaDismiss;

    hide(name);

    // The update card is not remembered: a declined update should be offered
    // again on the next visit, because the version behind it is still waiting
    // and may be the one carrying a fix.
    if (name !== 'update') remember(name);
});

/* ------------------------------------------------------------ this device -- */

/** Already installed and running as an app — nothing left to offer. */
const installed = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: minimal-ui)').matches ||
    // Safari's own flag, and the only one iOS sets.
    window.navigator.standalone === true;

/**
 * iOS Safari, where Add to Home Screen is a manual act.
 *
 * iPadOS 13 and later tell us they are a Mac, so a desktop user agent with a
 * touchscreen is treated as an iPad — no Mac has one, and a false positive
 * only shows instructions, never breaks anything.
 *
 * The in-app browsers are excluded by name. Chrome, Firefox and Edge on iOS
 * are all Safari underneath, but their Share menus add a plain bookmark that
 * opens back in the browser, so sending someone there would not install the
 * app the instructions promise.
 */
function isIosSafari() {
    const ua = window.navigator.userAgent;
    const iPad = /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;

    if (!/iPhone|iPad|iPod/.test(ua) && !iPad) return false;

    return !/CriOS|FxiOS|EdgiOS|OPiOS|GSA/.test(ua);
}

/* ---------------------------------------------------------- the install -- */

/**
 * The stashed `beforeinstallprompt`. It can be used exactly once, and only
 * from inside a user gesture — so it is held here until a button is pressed
 * rather than fired on arrival, which is also what the browser would refuse
 * to honour.
 */
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    // Without this the browser shows its own mini-infobar, and the shop ends
    // up with two install offers on screen at once.
    event.preventDefault();

    deferredPrompt = event;

    if (!dismissed('install')) show('install');
});

document.addEventListener('click', async (event) => {
    if (!event.target.closest('[data-pwa-install]')) return;

    if (!deferredPrompt) {
        hide('install');

        return;
    }

    // Take the event out of the variable before awaiting: the prompt cannot be
    // reused, and a second click arriving mid-await must not find it.
    const prompt = deferredPrompt;
    deferredPrompt = null;

    hide('install');

    try {
        await prompt.prompt();

        const { outcome } = await prompt.userChoice;

        // Declined at the browser's own dialog. Treated exactly like tapping
        // the card's ×, so the shop does not ask again tomorrow. If they
        // accept, `appinstalled` below does the clearing up.
        if (outcome === 'dismissed') remember('install');
    } catch {
        // The prompt was already consumed, or the browser refused it. The card
        // is down either way; nothing is owed to the visitor here.
    }
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    hide('install');
    hide('ios');

    // Clear the dismissal: if the app is ever removed, the offer should come
    // back rather than sit out the rest of the month.
    try {
        localStorage.removeItem(dismissKey('install'));
    } catch {
        /* see remember() */
    }
});

/*
 * iOS gets its card on a timer. There is no event to wait for, so the only
 * question is when — and the answer is not during the first paint, when the
 * visitor is still working out what the page is. Eight seconds in, they have
 * stayed long enough for the offer to be worth making.
 */
if (isIosSafari() && !installed() && !dismissed('ios')) {
    setTimeout(() => {
        // Checked again on the way in: they may have installed in the meantime
        // (iOS reloads the page into standalone mode), or dismissed the card
        // from another tab of the same shop. And if a real install prompt has
        // turned up since — a browser wearing an iOS user agent, which happens
        // — then that is the better offer and these instructions are wrong.
        if (installed() || dismissed('ios') || deferredPrompt) return;

        show('ios');
    }, 8000);
}

/* --------------------------------------------------- the service worker --
 *
 * Registered only from a built bundle. In development the assets come from the
 * Vite dev server on another origin and there is no /build directory for the
 * worker to precache, so a worker running here would do nothing but get in the
 * way of hot reload — and then linger, because a registration outlives the
 * page that made it. Hence the else branch: a developer who tested a
 * production build on localhost is unregistered back to a clean state the next
 * time they run `npm run dev`.
 *
 * Nothing is conditional on HTTPS, because nothing has to be: a browser only
 * exposes navigator.serviceWorker on a secure context at all, which means
 * https:// or localhost. In production that is enforced for us.
 */
if ('serviceWorker' in navigator) {
    if (import.meta.env.PROD) {
        // After `load`, so registering never competes with the first paint for
        // bandwidth on the slow connection that needs it most.
        window.addEventListener('load', register);
    } else {
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            registrations.forEach((registration) => registration.unregister());
        });
    }
}

async function register() {
    let registration;

    try {
        registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch {
        // A blocked worker (a browser setting, a private window, a failed
        // response) costs the visitor nothing: the shop is a server-rendered
        // site and every page of it still works. Nothing is reported.
        return;
    }

    watchForUpdate(registration);
}

/* --------------------------------------------------------------- updates -- */

/**
 * Whether a reload is already under way. `controllerchange` can fire more than
 * once, and reloading twice would at best flicker and at worst loop.
 */
let reloading = false;

/**
 * Offer the update when a second worker is installed and waiting.
 *
 * `registration.waiting` covers the worker that was already waiting when this
 * page loaded; `updatefound` covers one that installs while the visitor is
 * here. The `controller` check in the second is what separates an update from
 * a first install — on a first install a worker also reaches `installed`, but
 * there is no older version it is replacing and nothing to tell anyone about.
 */
function watchForUpdate(registration) {
    if (registration.waiting && navigator.serviceWorker.controller) {
        offer(registration.waiting);
    }

    registration.addEventListener('updatefound', () => {
        const incoming = registration.installing;

        if (!incoming) return;

        incoming.addEventListener('statechange', () => {
            if (incoming.state === 'installed' && navigator.serviceWorker.controller) {
                offer(incoming);
            }
        });
    });

    /*
     * A browser checks for a new worker on navigation, which an installed app
     * may not do for days — app.js swaps the page's regions over AJAX rather
     * than navigating, so the shop can be used for a long session without a
     * single document request. Checking when the app is brought back to the
     * foreground closes that gap, throttled so a visitor flicking between apps
     * is not firing a request every time.
     */
    let checked = Date.now();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;
        if (Date.now() - checked < 36e5) return;

        checked = Date.now();
        registration.update().catch(() => {
            /* offline, or the check failed: the next one will do */
        });
    });
}

/**
 * Show the update card, and wire its button to the waiting worker.
 *
 * The handshake is deliberately two-sided. The worker does not call
 * skipWaiting() on its own (see public/sw.js) — it waits for this message —
 * because taking over unasked would swap the assets under a page that is
 * part-way through a checkout. Only when the visitor presses the button does
 * the new worker activate, and `controllerchange` then tells us it is in
 * charge and the page can safely be reloaded onto it.
 */
function offer(worker) {
    show('update');

    const button = document.querySelector('[data-pwa-update]');

    if (!button) return;

    button.addEventListener(
        'click',
        () => {
            button.disabled = true;

            navigator.serviceWorker.addEventListener('controllerchange', () => {
                if (reloading) return;

                reloading = true;
                window.location.reload();
            });

            worker.postMessage({ type: 'SKIP_WAITING' });
        },
        { once: true },
    );
}
