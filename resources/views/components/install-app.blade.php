{{--
    The storefront's install affordances — all of them, in one place.

    Three cards live here and all three start hidden. resources/js/pwa.js is
    the only thing that reveals one, because only the browser can say which (if
    any) applies: whether this device can be installed to at all, whether it
    already has been, and whether a newer version of the service worker is
    waiting. Rendering the markup on the server and letting the client decide
    what to show keeps the Arabic copy in Blade with the rest of the shop's
    words, and keeps pwa.js free of markup to build.

      [data-pwa-install-card]  Chrome and the Chromium desktops. Shown only
                               after `beforeinstallprompt` fires, which is the
                               browser saying it is willing — so the button can
                               never be offered where it would do nothing.

      [data-pwa-ios-card]      iOS and iPadOS. Safari fires no such event and
                               never will, so there is nothing to press: the
                               card explains the Share → Add to Home Screen
                               route by hand. It is shown on a Safari that is
                               not already running standalone.

      [data-pwa-update-card]   A new worker has installed and is waiting. See
                               the update handshake in pwa.js.

    This sits outside every `data-shell-region` in layouts/shop.blade.php, so
    an AJAX navigation leaves it alone — a visitor part-way through reading the
    iOS instructions does not lose them by tapping a product.
--}}

@php
    // The cards share a shape: a floating card in the thumb's reach, clear of
    // the phone's fixed bottom bar (and of the home indicator under it), and
    // tucked into the start corner on a desktop where there is no such bar.
    $card = 'fixed inset-x-3 z-40 mx-auto max-w-md rounded-2xl border border-stone-200 bg-white/95 p-4
             shadow-lift backdrop-blur-xl bottom-[calc(5rem+env(safe-area-inset-bottom))]
             md:inset-x-auto md:bottom-4 md:start-4 md:mx-0';

    $primary = 'rounded-full bg-brand-600 px-5 py-2 text-sm font-bold text-white transition
                hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2
                focus-visible:outline-brand-700';

    $dismiss = 'shrink-0 rounded-full p-1.5 text-stone-400 transition hover:bg-stone-100 hover:text-stone-600';
@endphp

{{-- ─────────────────────────────── Android / desktop ────────────────────── --}}
<div data-pwa-install-card hidden class="{{ $card }}" role="dialog" aria-labelledby="pwa-install-title">
    <div class="flex items-start gap-3">
        <img src="{{ asset('images/logo-192.png') }}" alt="" width="192" height="192"
             class="size-11 shrink-0 rounded-full">

        <div class="min-w-0 flex-1">
            <p id="pwa-install-title" class="font-bold text-stone-900">ثبّت {{ $storeName }} على جهازك</p>
            <p class="mt-0.5 text-sm text-stone-600">تصفّح أسرع، وأيقونة على شاشتك الرئيسية.</p>
        </div>

        <button type="button" data-pwa-dismiss="install" class="{{ $dismiss }}" aria-label="إغلاق">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="mt-3 flex justify-end">
        {{-- data-pwa-install is the hook, not the card: any button anywhere on
             the storefront can carry it and will open the same prompt. --}}
        <button type="button" data-pwa-install class="{{ $primary }}">تثبيت التطبيق</button>
    </div>
</div>

{{-- ──────────────────────────────────── iOS ─────────────────────────────── --}}
<div data-pwa-ios-card hidden class="{{ $card }}" role="dialog" aria-labelledby="pwa-ios-title">
    <div class="flex items-start gap-3">
        <img src="{{ asset('images/logo-192.png') }}" alt="" width="192" height="192"
             class="size-11 shrink-0 rounded-full">

        <div class="min-w-0 flex-1">
            <p id="pwa-ios-title" class="font-bold text-stone-900">أضف {{ $storeName }} إلى الشاشة الرئيسية</p>
            <p class="mt-0.5 text-sm text-stone-600">من متصفح Safari، خطوتان فقط:</p>
        </div>

        <button type="button" data-pwa-dismiss="ios" class="{{ $dismiss }}" aria-label="إغلاق">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- The two glyphs are drawn rather than described, because they are what
         the visitor is looking for on their own screen: the share box with the
         arrow out of it, and the plus in a rounded square. --}}
    <ol class="mt-3 space-y-2 text-sm text-stone-700">
        <li class="flex items-center gap-2">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-brand-700">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 15V3m0 0L8.25 6.75M12 3l3.75 3.75M4.5 12.75V18a3 3 0 0 0 3 3h9a3 3 0 0 0 3-3v-5.25"/>
                </svg>
            </span>
            اضغط زر <span class="font-bold">المشاركة</span> في شريط المتصفح
        </li>
        <li class="flex items-center gap-2">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-brand-700">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 7.5v9m4.5-4.5h-9M4.5 7.5A3 3 0 0 1 7.5 4.5h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Z"/>
                </svg>
            </span>
            اختر <span class="font-bold">إضافة إلى الشاشة الرئيسية</span>
        </li>
    </ol>
</div>

{{-- ─────────────────────────────────── update ───────────────────────────── --}}
<div data-pwa-update-card hidden class="{{ $card }}" role="alert" aria-labelledby="pwa-update-title">
    <div class="flex items-start gap-3">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M16.023 9.348h4.992V4.356M3.985 14.652H8.98v4.992m10.055-10.296a8.25 8.25 0 0 0-14.3-2.69L3.985 8.65m0 6.002 1.75 2.684a8.25 8.25 0 0 0 14.3-2.69"/>
            </svg>
        </span>

        <div class="min-w-0 flex-1">
            <p id="pwa-update-title" class="font-bold text-stone-900">يتوفر إصدار جديد</p>
            <p class="mt-0.5 text-sm text-stone-600">حدّث الآن للحصول على آخر نسخة من المتجر.</p>
        </div>

        <button type="button" data-pwa-dismiss="update" class="{{ $dismiss }}" aria-label="إغلاق">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="mt-3 flex justify-end">
        <button type="button" data-pwa-update class="{{ $primary }}">تحديث الآن</button>
    </div>
</div>
