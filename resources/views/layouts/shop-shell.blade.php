{{--
    The AJAX half of layouts/shop.blade.php.

    When resources/js/app.js navigates it asks for the same route with an
    `X-Page-Visit` header, and App\Http\Middleware\ShareStorefrontData points
    `$shopLayout` here instead. The page renders exactly as it always does —
    same controller, same view, same sections — but only the regions a
    navigation can change come back:

      <div data-shell-region="#site-main"> … </div>

    The attribute is the CSS selector of the element in the live document whose
    innerHTML it replaces, so adding a region is a matter of giving it an id in
    both layouts and listing it here. Nothing else is sent: the promises strip,
    <head>, the preloader and the scripts stay where they are.

    Why a second layout rather than shipping the whole page and picking it
    apart on the client: the header's mega menu and the footer are about 45KB
    of the ~52KB a cart page weighs. Sending them on every click would make a
    navigation cost more than the page it replaces.
--}}
@php
    // A defined section comes back raw from yieldContent (only the default is
    // escaped), and these land in attributes — so Blade escapes them below.
    $shellTitle = trim($__env->yieldContent('title', $storeName)).' — '.$storeName;
    $shellDescription = trim($__env->yieldContent('description', $storeTagline));

    // Who the live document was rendered for, as app.js read it off <body>.
    // Equal means the header and footer on screen are still correct.
    $shellChrome = (string) (auth()->id() ?? 'guest');
    $chromeIsStale = request()->header('X-Page-Chrome') !== $shellChrome;

    /*
     * The phone's category rail is either the whole tree or nothing at all —
     * it marks no active category, so every page that shows it shows the same
     * markup. Only its presence can change, so that is all app.js reports back
     * and all this has to answer: moving between the home page and a category
     * re-sends nothing, which on this catalogue is 40KB of accordion saved.
     */
    $railIsVisible = request()->routeIs('home', 'catalog', 'category');
    $railIsStale = request()->header('X-Page-Rail') !== ($railIsVisible ? '1' : '0');
@endphp
<div data-page-shell
     data-title="{{ $shellTitle }}"
     data-description="{{ $shellDescription }}"
     data-chrome="{{ $shellChrome }}"
     data-rail="{{ $railIsVisible ? '1' : '0' }}"
     data-cart-count="{{ $cartCount }}">

    {{-- Signing in or out: the account menu, the cart owner and the footer's
         account column all change, so the furniture is re-sent. On every
         ordinary navigation these two are absent and left untouched. --}}
    @if ($chromeIsStale)
        <div data-shell-region="#site-header">@include('layouts.partials.header')</div>
        <div data-shell-region="#site-footer">@include('layouts.partials.footer')</div>
    @endif

    @if ($railIsStale)
        <div data-shell-region="#site-rail">@include('layouts.partials.mobile-rail')</div>
    @endif

    <div data-shell-region="#site-main">
        <x-alert />
        @yield('content')
    </div>

    <div data-shell-region="#site-bar">
        @hasSection('mobile-bar')
            @yield('mobile-bar')
        @else
            @include('layouts.partials.mobile-bar')
        @endif
    </div>
</div>
