<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;

/**
 * The offline fallback, precached by public/sw.js at install and served from
 * the device whenever a navigation cannot reach us.
 *
 * It is deliberately thin. Whatever this returns is frozen onto every visitor's
 * phone until the worker's next version, so it may hold nothing that can go out
 * of date and nothing about the person asking — which is also why its route
 * drops ShareStorefrontData: the header's cart count and the signed-in
 * customer's menu have no business in a file that outlives their session.
 *
 * The shop's name is the one setting it takes, because a page that cannot say
 * which shop failed to load is not much of a fallback. A rename leaves the
 * cached copy stale until the worker is reinstalled; that is the price of a
 * page that works with the network down, and it is the right way round.
 */
class OfflineController extends Controller
{
    public function __invoke(): View
    {
        return view('shop.offline', [
            'storeName' => Setting::get('store_name', config('app.name')),
        ]);
    }
}
