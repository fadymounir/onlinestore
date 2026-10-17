<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $storeName) — {{ $storeName }}</title>
    <meta name="description" content="@yield('description', $storeTagline)">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta property="og:image" content="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-800 antialiased">

{{-- announcement --}}
@if (filled($announcement))
<div class="bg-stone-900 text-stone-100">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs">
        <p>{{ $announcement }}</p>
        @if ($storePhone)
            <a href="tel:{{ $storePhone }}" class="hover:text-amber-300">خدمة العملاء: {{ $storePhone }}</a>
        @endif
    </div>
</div>
@endif

<header class="sticky top-0 z-30 border-b border-stone-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-4">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
            <img src="{{ asset('images/logo-192.png') }}" alt="{{ $storeName }}" width="192" height="192"
                 class="size-11 rounded-full">
            {{-- The badge carries its own wordmark, so the name is a companion, not a repeat. --}}
            <span class="hidden text-xl font-bold text-stone-900 sm:inline">{{ $storeName }}</span>
        </a>

        <form action="{{ route('catalog') }}" method="GET" class="hidden flex-1 md:block">
            <div class="relative">
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="ابحث عن فستان، قميص، حذاء…"
                       class="w-full rounded-full border border-stone-300 bg-stone-50 py-2.5 pe-4 ps-11 text-sm
                              outline-none focus:border-amber-600 focus:bg-white focus:ring-2 focus:ring-amber-600/20">
                <svg class="pointer-events-none absolute top-2.5 start-3.5 size-5 text-stone-400" fill="none"
                     stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="m21 21-4.3-4.3m1.8-4.7a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/>
                </svg>
            </div>
        </form>

        <nav class="flex items-center gap-1 ms-auto md:ms-0">
            @auth
                <a href="{{ route('wishlist') }}" title="المفضّلة"
                   class="rounded-lg p-2 text-stone-600 hover:bg-stone-100 hover:text-amber-700">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M21 8.25c0-2.5-2-4.5-4.5-4.5-1.7 0-3.2.9-4 2.3-.8-1.4-2.3-2.3-4-2.3C2 3.75 0 5.75 0 8.25c0 6 9 11 12 12.75 3-1.75 12-6.75 12-12.75Z" transform="translate(0 .5) scale(.92)"/>
                    </svg>
                </a>
                <div class="group relative">
                    <button type="button" class="rounded-lg p-2 text-stone-600 hover:bg-stone-100 hover:text-amber-700">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17 17 0 0 1 12 21.75c-2.7 0-5.2-.6-7.5-1.65Z"/>
                        </svg>
                    </button>
                    <div class="invisible absolute end-0 z-40 w-48 rounded-xl border border-stone-200 bg-white p-2 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100">
                        <p class="truncate px-3 pb-2 pt-1 text-xs text-stone-500">{{ auth()->user()->name }}</p>
                        <a href="{{ route('account.orders.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-stone-100">طلباتي</a>
                        <a href="{{ route('account.addresses.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-stone-100">عناويني</a>
                        <a href="{{ route('wishlist') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-stone-100">المفضّلة</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full rounded-lg px-3 py-2 text-start text-sm text-red-700 hover:bg-red-50">
                                تسجيل الخروج
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100">
                    تسجيل الدخول
                </a>
                <a href="{{ route('register') }}" class="hidden rounded-lg bg-stone-900 px-3 py-2 text-sm font-medium text-white hover:bg-stone-800 sm:block">
                    حساب جديد
                </a>
            @endauth

            <a href="{{ route('cart.index') }}" title="السلة"
               class="relative rounded-lg p-2 text-stone-600 hover:bg-stone-100 hover:text-amber-700">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.4a1.5 1.5 0 0 1 1.47 1.2L5.4 6m0 0 1.8 8.5a1.5 1.5 0 0 0 1.47 1.2h8.2a1.5 1.5 0 0 0 1.46-1.14l1.6-6.4A.9.9 0 0 0 19.06 6H5.4Zm3.6 14.25a1.125 1.125 0 1 1-2.25 0 1.125 1.125 0 0 1 2.25 0Zm9 0a1.125 1.125 0 1 1-2.25 0 1.125 1.125 0 0 1 2.25 0Z"/>
                </svg>
                {{-- Always rendered, hidden at zero: the AJAX layer repaints its
                     text after every cart change and cannot create a missing node.
                     Visibility rides on hidden/flex rather than the `hidden`
                     attribute, which `display:flex` would otherwise win against. --}}
                <span id="cart-count"
                      class="{{ $cartCount < 1 ? 'hidden' : 'flex' }} absolute -top-0.5 -start-0.5 size-5 items-center justify-center rounded-full bg-amber-600 text-[10px] font-bold text-white">{{ $cartCount }}</span>
            </a>
        </nav>
    </div>

    {{-- category nav: full tree, any depth --}}
    <x-mega-menu :categories="$navCategories" />

</header>

{{-- mobile search --}}
<form action="{{ route('catalog') }}" method="GET" class="border-b border-stone-200 bg-white px-4 py-3 md:hidden">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="ابحث…"
           class="w-full rounded-full border border-stone-300 bg-stone-50 px-4 py-2.5 text-sm outline-none focus:border-amber-600">
</form>

<main class="mx-auto max-w-7xl px-4 py-8">
    <x-alert />
    @yield('content')
</main>

<footer class="mt-16 border-t border-stone-200 bg-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="mb-3 flex items-center gap-2.5">
                <img src="{{ asset('images/logo-192.png') }}" alt="" width="192" height="192" class="size-10 rounded-full">
                <h2 class="text-lg font-bold text-stone-900">{{ $storeName }}</h2>
            </div>
            <p class="text-sm leading-relaxed text-stone-600">{{ $storeTagline }}</p>
        </div>
        <div>
            <h3 class="mb-3 text-sm font-semibold text-stone-900">التصنيفات</h3>
            <ul class="space-y-2 text-sm text-stone-600">
                @foreach ($navCategories->take(5) as $cat)
                    <li><a href="{{ route('category', $cat) }}" class="hover:text-amber-700">{{ $cat->name }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3 class="mb-3 text-sm font-semibold text-stone-900">حسابي</h3>
            <ul class="space-y-2 text-sm text-stone-600">
                @auth
                    <li><a href="{{ route('account.orders.index') }}" class="hover:text-amber-700">طلباتي</a></li>
                    <li><a href="{{ route('account.addresses.index') }}" class="hover:text-amber-700">عناويني</a></li>
                    <li><a href="{{ route('wishlist') }}" class="hover:text-amber-700">المفضّلة</a></li>
                @else
                    <li><a href="{{ route('login') }}" class="hover:text-amber-700">تسجيل الدخول</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-amber-700">إنشاء حساب</a></li>
                @endauth
                <li><a href="{{ route('cart.index') }}" class="hover:text-amber-700">سلة الشراء</a></li>
            </ul>
        </div>
        <div>
            <h3 class="mb-3 text-sm font-semibold text-stone-900">تواصل معنا</h3>
            <ul class="space-y-2 text-sm text-stone-600">
                @if ($storePhone)<li>هاتف: <a href="tel:{{ $storePhone }}" dir="ltr" class="hover:text-amber-700">{{ $storePhone }}</a></li>@endif
                @if ($storeWhatsapp)<li>واتساب: <span dir="ltr">{{ $storeWhatsapp }}</span></li>@endif
                @if ($storeEmail)<li><a href="mailto:{{ $storeEmail }}" class="hover:text-amber-700">{{ $storeEmail }}</a></li>@endif
            </ul>
            <div class="mt-4 flex gap-3">
                @foreach (array_filter($socials) as $name => $url)
                    <a href="{{ $url }}" rel="noopener" class="text-xs text-stone-500 hover:text-amber-700">{{ $name }}</a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="border-t border-stone-100 py-5 text-center text-xs text-stone-500">
        جميع الحقوق محفوظة &copy; {{ now()->year }} {{ $storeName }} — الدفع عند الاستلام في كل محافظات مصر
    </div>
</footer>

</body>
</html>
