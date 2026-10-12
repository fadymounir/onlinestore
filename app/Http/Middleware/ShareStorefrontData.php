<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Setting;
use App\Services\CartService;
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
        View::share([
            // Queried per request rather than cached: Laravel 13 ships
            // cache.serializable_classes => false, so caching Eloquent models
            // returns __PHP_Incomplete_Class. One query is cheaper than
            // weakening that hardening — the nested set comes back flat and is
            // linked into a tree in memory, so depth costs nothing extra.
            'navCategories' => Category::tree(onlyActive: true),
            'cartCount' => app(CartService::class)->count(),
            'storeName' => Setting::get('store_name', config('app.name')),
            'storeTagline' => Setting::get('store_tagline', ''),
            'announcement' => Setting::get('announcement_text', ''),
            'storePhone' => Setting::get('store_phone'),
            'storeWhatsapp' => Setting::get('store_whatsapp'),
            'storeEmail' => Setting::get('store_email'),
            'socials' => [
                'facebook' => Setting::get('facebook_url'),
                'instagram' => Setting::get('instagram_url'),
                'tiktok' => Setting::get('tiktok_url'),
            ],
        ]);

        return $next($request);
    }
}
