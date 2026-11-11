{{--
    The storefront's one full-page layout.

    Everything a navigation can change is wrapped in an element with an id, and
    those ids are the contract between this file and two others:

      layouts/shop-shell.blade.php   re-renders the same regions, nothing else
      resources/js/app.js            swaps them in place on a click

    So a region here must stay a container whose *contents* are replaceable:
    put the per-page markup inside it, never on it. Everything outside a region
    — the promises strip, <head>, the preloader — is rendered once per real
    page load and is never touched again.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $storeName) — {{ $storeName }}</title>
    <meta name="description" content="@yield('description', $storeTagline)">
    <meta name="theme-color" content="#1c1917">
    {{-- The .ico carries 16/32/48 for the tab, the 192 is what Android and
         the install prompt reach for. All of it is cut from the badge in
         resources/images — see `npm run logo`. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta property="og:image" content="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- The loader below is hidden until this line runs, so a visitor without
         JavaScript never meets a screen nothing will take away. --}}
    <script>document.documentElement.classList.add('js')</script>
</head>
{{--
    pb-20 on mobile: the fixed bottom bar would otherwise sit on the footer.

    data-chrome is who the page was rendered for. app.js sends it back with
    every navigation, and the shell answers with a fresh header and footer only
    when it no longer matches — signing in or out is the one thing a navigation
    can do that changes the furniture around the page.
--}}
<body class="min-h-screen bg-paper pb-20 font-sans text-stone-700 antialiased md:pb-0"
      data-chrome="{{ auth()->id() ?? 'guest' }}">

{{-- First paint. Styled in resources/css/app.css, dismissed in app.js — it
     only ever appears on a load slow enough to be worth covering. --}}
<div class="app-preloader" aria-hidden="true">
    <div class="app-preloader__inner">
        <img src="{{ asset('images/logo-192.png') }}" alt="" width="192" height="192" class="app-preloader__logo">
        <span class="app-preloader__name">{{ $storeName }}</span>
        <span class="app-preloader__track"><span class="app-preloader__bar"></span></span>
    </div>
</div>

<header id="site-header" class="site-header sticky top-0 z-30 border-b border-stone-200/70 bg-paper/85 backdrop-blur-xl">
    @include('layouts.partials.header')
</header>

<div id="site-rail">
    @include('layouts.partials.mobile-rail')
</div>

{{-- tabindex=-1: after an AJAX navigation app.js moves focus here, so a
     keyboard or screen-reader user lands on the new page rather than at the
     top of a header they had already passed. --}}
<main id="site-main" tabindex="-1" class="mx-auto max-w-7xl px-4 py-6 outline-none sm:py-10">
    <x-alert />
    @yield('content')
</main>

@include('layouts.partials.promises')

<footer id="site-footer" class="bg-ink text-stone-400">
    @include('layouts.partials.footer')
</footer>

<div id="site-bar">
    @hasSection('mobile-bar')
        @yield('mobile-bar')
    @else
        @include('layouts.partials.mobile-bar')
    @endif
</div>

</body>
</html>
