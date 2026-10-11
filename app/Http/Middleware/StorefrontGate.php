<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the `maintenance_mode` setting for the storefront only. The panel
 * keeps working so staff can switch it back off, and a signed-in admin can
 * still browse the shop to check their changes.
 */
class StorefrontGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $isClosed = (bool) Setting::get('maintenance_mode', '0');

        if ($isClosed && ! Auth::guard('admin')->check()) {
            return response()->view('shop.maintenance', [
                'storeName' => Setting::get('store_name', config('app.name')),
                'storePhone' => Setting::get('store_phone'),
            ], 503);
        }

        return $next($request);
    }
}
