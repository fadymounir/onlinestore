<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\CheckoutService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header and footer chrome for the storefront. Shared via View::share rather
 * than a view composer so it reaches the layout AND every child view's
 * sections, which are compiled with their own data.
 */
class ShareStorefrontData
{
    public function handle(Request $request, Closure $next): Response
    {
        $whatsapp = Setting::get('store_whatsapp');

        View::share([
            // Which layout every storefront page extends. app.js marks its
            // navigations with `X-Page-Visit`, and those get the shell — the
            // same page, rendered down to the regions a click can change.
            // Shared rather than resolved in each controller so a new page is
            // AJAX-navigable the moment it says `@extends($shopLayout)`.
            'shopLayout' => $request->hasHeader('X-Page-Visit')
                ? 'layouts.shop-shell'
                : 'layouts.shop',
            // Queried per request rather than cached: Laravel 13 ships
            // cache.serializable_classes => false, so caching Eloquent models
            // returns __PHP_Incomplete_Class. One query is cheaper than
            // weakening that hardening — the nested set comes back flat and is
            // linked into a tree in memory, so depth costs nothing extra.
            'navCategories' => Category::tree(onlyActive: true),
            'cartCount' => app(CartService::class)->count(),
            // Orders paused: the cart's checkout buttons stand down, but
            // everything else on the storefront carries on as normal.
            'orderingPaused' => app(CheckoutService::class)->ordersPaused(),
            // Addresses switched off: the account menu and the footer stop
            // offering a page that now redirects away.
            'addressesEnabled' => Setting::customerAddressesEnabled(),
            'orderingPausedMessage' => CheckoutService::PAUSED_MESSAGE,
            // Admin-authored pages the panel marked for the footer.
            'footerPages' => Page::inFooter()->get(['id', 'title', 'menu_title', 'slug']),
            'storeName' => Setting::get('store_name', config('app.name')),
            'storeTagline' => Setting::get('store_tagline', ''),
            'storeLogo' => Setting::logoUrl(),
            'storePhone' => Setting::get('store_phone'),
            'storeWhatsapp' => $whatsapp,
            'storeWhatsappUrl' => $this->whatsappUrl($whatsapp),
            'storeEmail' => Setting::get('store_email'),
            'socials' => [
                'facebook' => Setting::get('facebook_url'),
                'instagram' => Setting::get('instagram_url'),
                'tiktok' => Setting::get('tiktok_url'),
            ],
        ]);

        $response = $next($request);

        /*
         * One URL now has two representations — the full page and the shell —
         * and the request header is the only thing that tells them apart. A
         * browser or proxy cache that did not know this would happily hand a
         * bare fragment to someone typing the URL in, so say so. `X-Fragment`
         * is the same contract for the partial swaps in SendsFragments.
         */
        // Joined into one header rather than passed as a list, which Symfony
        // would emit as a Vary line each — correct HTTP, but harder to read
        // back and not what every cache handles equally well.
        $response->setVary(implode(', ', array_unique([...$response->getVary(), 'X-Page-Visit', 'X-Fragment'])));

        return $response;
    }

    /**
     * wa.me wants a full international number with no punctuation, but the
     * setting holds whatever the shop types — usually an Egyptian mobile in
     * local form, "0100 123 4567". Strip the formatting and swap the national
     * trunk 0 for Egypt's country code; a number already in international form
     * is left as it is.
     */
    private function whatsappUrl(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if (blank($digits)) {
            return null;
        }

        return 'https://wa.me/'.(str_starts_with($digits, '0') ? '20'.substr($digits, 1) : $digits);
    }
}
