{{--
    What makes the storefront installable.

    The manifest covers Android, Chrome and the desktop Chromium browsers on
    its own — name, icons, colours, display mode, all of it in one file. The
    rest of this partial exists for iOS, which reads no manifest at all: Safari
    decides what an added-to-home-screen site is called and how it opens from
    these meta tags, and from the apple-touch-icon in favicons.blade.php.

    `apple-mobile-web-app-capable` is what drops Safari's address bar and
    toolbar, which is the whole difference between a bookmark and something
    that feels like an app. `mobile-web-app-capable` is the standardised name
    for the same thing; both are given because the old one is still what
    Safari reads.

    The status bar is left `default` — dark text on light — because the page
    ground is paper. `black-translucent` would put the page under the clock
    instead, and this layout has a sticky header that would end up behind it.
--}}
<link rel="manifest" href="{{ asset('manifest.json') }}">

<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

{{-- The name under the icon on an iPhone home screen. Kept short for the same
     reason manifest.json's short_name is: iOS truncates at around a dozen
     characters. --}}
<meta name="apple-mobile-web-app-title" content="{{ Str::limit($storeName, 12, '') }}">
