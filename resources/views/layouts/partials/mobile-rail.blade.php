{{--
    The phone's category rail, and the whole tree one tap away.

    Only on the browsing pages: on a product, cart or account page it would
    push the content that was asked for a hundred pixels down, and the bottom
    bar's "المتجر" already leads back to the catalogue. It lives outside the
    sticky header so it scrolls out of the way.
--}}

@if (request()->routeIs('home', 'catalog', 'category'))
    <x-mega-menu :categories="$navCategories" variant="mobile" />
@endif
