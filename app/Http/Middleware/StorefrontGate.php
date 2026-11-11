<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the `maintenance_mode` setting for the storefront only. The panel
 * keeps working so staff can switch it back off; the shop itself closes for
 * everyone, signed-in admins included, so what staff see while testing is
 * what a customer sees.
 */
class StorefrontGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isPanelRequest($request)) {
            return $next($request);
        }

        if (! (bool) Setting::get('maintenance_mode', '0')) {
            return $next($request);
        }

        // This runs before ShareStorefrontData, so the closed-shop page is
        // handed its own chrome — it cannot read the shared view data.
        return response()->view('shop.maintenance', [
            'storeName' => Setting::get('store_name', config('app.name')),
            'storePhone' => Setting::get('store_phone'),
            'storeLogo' => Setting::logoUrl(),
        ], 503);
    }

    /**
     * The panel's own routes carry their own middleware stack, but Livewire
     * registers its update/upload endpoints in the `web` group — and Filament
     * drives every panel interaction, the login form included, through them.
     * Closing those would lock staff out of the very screen that lifts
     * maintenance mode, so they are never gated.
     */
    private function isPanelRequest(Request $request): bool
    {
        return $request->routeIs('*livewire*')
            || $request->is('admin', 'admin/*');
    }
}
