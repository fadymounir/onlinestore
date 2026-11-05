{{--
    The phone's thumb zone: the five destinations a shopper moves between.
    Which tab is lit depends on the current route, so the AJAX shell re-sends
    this on every navigation.

    A page with something better to put in the thumb zone defines a
    `mobile-bar` section instead — see shop/product.blade.php, where the one
    control worth a fixed bar is "add to cart".
--}}

<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 pb-[env(safe-area-inset-bottom)]
            backdrop-blur-xl md:hidden"
     aria-label="تنقل سريع">
    <ul class="mx-auto flex max-w-lg items-stretch">
        @php
            $tabs = [
                ['label' => 'الرئيسية', 'url' => route('home'), 'active' => request()->routeIs('home'),
                 'icon' => 'm2.25 12 8.95-8.95a1.13 1.13 0 0 1 1.6 0L21.75 12M4.5 9.75v10.13c0 .62.5 1.12 1.13 1.12H9.75v-4.88c0-.62.5-1.12 1.13-1.12h2.25c.62 0 1.12.5 1.12 1.13V21h4.13c.62 0 1.12-.5 1.12-1.13V9.75'],
                ['label' => 'المتجر', 'url' => route('catalog'), 'active' => request()->routeIs('catalog') || request()->routeIs('category'),
                 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25Zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
                // Guests have no wishlist; sending them to the login screen is the
                // same place the guarded route would have redirected them to anyway.
                ['label' => 'المفضّلة', 'url' => auth()->check() ? route('wishlist') : route('login'),
                 'active' => request()->routeIs('wishlist'),
                 'icon' => 'M21 8.25c0-2.5-2-4.5-4.5-4.5-1.7 0-3.2.9-4 2.3-.8-1.4-2.3-2.3-4-2.3-2.5 0-4.5 2-4.5 4.5 0 6 8.5 10.5 8.5 10.5S21 14.25 21 8.25Z'],
                ['label' => 'السلة', 'url' => route('cart.index'), 'active' => request()->routeIs('cart.*'), 'badge' => true,
                 'icon' => 'M2.25 3h1.4a1.5 1.5 0 0 1 1.47 1.2L5.4 6m0 0 1.8 8.5a1.5 1.5 0 0 0 1.47 1.2h8.2a1.5 1.5 0 0 0 1.46-1.14l1.6-6.4A.9.9 0 0 0 19.06 6H5.4Zm3.6 14.25a1.125 1.125 0 1 1-2.25 0 1.125 1.125 0 0 1 2.25 0Zm9 0a1.125 1.125 0 1 1-2.25 0 1.125 1.125 0 0 1 2.25 0Z'],
                ['label' => auth()->check() ? 'حسابي' : 'دخول',
                 'url' => auth()->check() ? route('account.orders.index') : route('login'),
                 'active' => request()->routeIs('account.*') || request()->routeIs('login'),
                 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17 17 0 0 1 12 21.75c-2.7 0-5.2-.6-7.5-1.65Z'],
            ];
        @endphp

        @foreach ($tabs as $tab)
            <li class="flex-1">
                <a href="{{ $tab['url'] }}" @class([
                       'flex flex-col items-center gap-1 py-2.5 text-[11px] font-bold transition',
                       'text-brand-700' => $tab['active'],
                       'text-stone-500' => ! $tab['active'],
                   ])>
                    <span class="relative">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                        </svg>
                        @if ($tab['badge'] ?? false)
                            <span data-cart-count
                                  class="{{ $cartCount < 1 ? 'hidden' : 'flex' }} absolute -top-1 -start-2 size-4
                                         items-center justify-center rounded-full bg-brand-500 text-[9px]
                                         font-bold text-white">{{ $cartCount }}</span>
                        @endif
                    </span>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
