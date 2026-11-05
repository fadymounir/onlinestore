{{--
    The sticky header: logo, search, account menu, cart badge and the desktop
    category bar.

    A partial because it is rendered from two places — layouts/shop.blade.php
    for a full page load, and layouts/shop-shell.blade.php when the AJAX layer
    asks for a navigation. The shell only sends it when the signed-in identity
    has changed, which is the one thing on it that a navigation can alter.
--}}

    <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-2.5 sm:gap-4 md:py-3.5">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
            <img src="{{ asset('images/logo-192.png') }}" alt="{{ $storeName }}" width="192" height="192"
                 class="size-11 rounded-2xl ring-1 ring-stone-900/5">
            {{-- The badge carries its own wordmark, so the name is a companion, not a repeat. --}}
            <span class="max-w-[38vw] truncate text-lg font-extrabold tracking-tight text-stone-900">{{ $storeName }}</span>
        </a>

        <form action="{{ route('catalog') }}" method="GET" class="hidden flex-1 md:block">
            <div class="group relative">
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="ابحث عن سلسلة، حلق، ساعة…"
                       class="w-full rounded-full border border-stone-200 bg-white/80 py-3 pe-28 ps-11 text-sm
                              shadow-soft outline-none transition
                              placeholder:text-stone-400 focus:border-brand-400 focus:bg-white
                              focus:ring-4 focus:ring-brand-500/10">
                <svg class="pointer-events-none absolute top-3 start-4 size-5 text-stone-400 transition
                            group-focus-within:text-brand-600"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="m21 21-4.3-4.3m1.8-4.7a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/>
                </svg>
                <button type="submit"
                        class="absolute bottom-1.5 end-1.5 top-1.5 rounded-full bg-stone-900 px-5 text-xs
                               font-bold text-white transition hover:bg-stone-800">
                    بحث
                </button>
            </div>
        </form>

        <nav class="ms-auto flex items-center gap-1 md:ms-0">
            @auth
                <a href="{{ route('wishlist') }}" title="المفضّلة" aria-label="المفضّلة"
                   class="hidden size-11 items-center justify-center rounded-2xl text-stone-500 transition
                          hover:bg-white hover:text-brand-700 hover:shadow-soft sm:flex">
                    <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.5-2-4.5-4.5-4.5-1.7 0-3.2.9-4 2.3-.8-1.4-2.3-2.3-4-2.3-2.5 0-4.5 2-4.5 4.5 0 6 8.5 10.5 8.5 10.5S21 14.25 21 8.25Z"/>
                    </svg>
                </a>

                <div class="group relative">
                    <button type="button" aria-label="حسابي"
                            class="flex size-11 items-center justify-center rounded-2xl text-stone-500 transition
                                   hover:bg-white hover:text-brand-700 hover:shadow-soft">
                        <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17 17 0 0 1 12 21.75c-2.7 0-5.2-.6-7.5-1.65Z"/>
                        </svg>
                    </button>
                    <div class="invisible absolute end-0 top-full z-40 w-56 translate-y-1 pt-2 opacity-0 transition
                                group-hover:visible group-hover:translate-y-0 group-hover:opacity-100
                                group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                        <div class="rounded-2xl border border-stone-200/80 bg-white p-2 shadow-lift">
                            <p class="truncate px-3 pb-2 pt-1.5 text-xs text-stone-500">
                                أهلاً، <span class="font-bold text-stone-800">{{ auth()->user()->name }}</span>
                            </p>
                            <a href="{{ route('account.orders.index') }}" class="block rounded-xl px-3 py-2 text-sm transition hover:bg-brand-50 hover:text-brand-800">طلباتي</a>
                            <a href="{{ route('account.addresses.index') }}" class="block rounded-xl px-3 py-2 text-sm transition hover:bg-brand-50 hover:text-brand-800">عناويني</a>
                            <a href="{{ route('wishlist') }}" class="block rounded-xl px-3 py-2 text-sm transition hover:bg-brand-50 hover:text-brand-800">المفضّلة</a>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full rounded-xl px-3 py-2 text-start text-sm text-red-700 transition hover:bg-red-50">
                                    تسجيل الخروج
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="rounded-full px-3 py-2 text-sm font-semibold text-stone-700 transition hover:text-brand-700">
                    تسجيل الدخول
                </a>
                <a href="{{ route('register') }}"
                   class="hidden rounded-full bg-stone-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-stone-800 sm:block">
                    حساب جديد
                </a>
            @endauth

            <a href="{{ route('cart.index') }}" title="السلة" aria-label="السلة"
               class="relative flex size-11 items-center justify-center rounded-2xl bg-stone-900 text-white
                      transition hover:bg-stone-800">
                <svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4a1.5 1.5 0 0 1 1.47 1.2L5.4 6m0 0 1.8 8.5a1.5 1.5 0 0 0 1.47 1.2h8.2a1.5 1.5 0 0 0 1.46-1.14l1.6-6.4A.9.9 0 0 0 19.06 6H5.4Zm3.6 14.25a1.125 1.125 0 1 1-2.25 0 1.125 1.125 0 0 1 2.25 0Zm9 0a1.125 1.125 0 1 1-2.25 0 1.125 1.125 0 0 1 2.25 0Z"/>
                </svg>
                {{-- Always rendered, hidden at zero: the AJAX layer repaints its
                     text after every cart change and cannot create a missing node.
                     Visibility rides on hidden/flex rather than the `hidden`
                     attribute, which `display:flex` would otherwise win against. --}}
                <span id="cart-count" data-cart-count
                      class="{{ $cartCount < 1 ? 'hidden' : 'flex' }} absolute -top-1 -start-1 size-5 items-center
                             justify-center rounded-full bg-brand-500 text-[10px] font-bold text-white
                             ring-2 ring-paper">{{ $cartCount }}</span>
            </a>
        </nav>
    </div>

    {{-- Mobile search stays inside the sticky header — on a phone, search is
         the fastest route to a product and should never scroll away. The
         category rail below does not: see the <x-mega-menu> after </header>. --}}
    <form action="{{ route('catalog') }}" method="GET" class="px-4 pb-2.5 md:hidden">
        <div class="relative">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="ابحث عن منتج…"
                   class="w-full rounded-full border border-stone-200 bg-white py-3 pe-4 ps-11 text-sm
                          shadow-soft outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-500/10">
            <svg class="pointer-events-none absolute top-3.5 start-4 size-5 text-stone-400" fill="none"
                 stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" d="m21 21-4.3-4.3m1.8-4.7a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/>
            </svg>
        </div>
    </form>

    {{-- category bar: full tree, any depth --}}
    <x-mega-menu :categories="$navCategories" variant="desktop" />

