{{--
    The tab icon.

    With no upload the curated set is used: the .ico carries 16/32/48 for the
    tab, the 192 is what Android and the install prompt reach for, and the
    apple-touch-icon is Safari's. All three are cut from the badge in
    resources/images by `npm run logo`.

    An uploaded logo replaces the lot with itself. Its dimensions and format
    are whatever the shop chose, so no `sizes` or `type` is claimed for it —
    an honest omission beats a wrong hint, and browsers scale it themselves.
--}}
@if (App\Models\Setting::hasCustomLogo())
    <link rel="icon" href="{{ $storeLogo }}">
    <link rel="apple-touch-icon" href="{{ $storeLogo }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
@endif
