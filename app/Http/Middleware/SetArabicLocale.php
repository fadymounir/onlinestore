<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The store is Arabic-only. This pins the locale on every request so no
 * package, session value or Accept-Language header can switch it.
 */
class SetArabicLocale
{
    public const LOCALE = 'ar';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::LOCALE);
        Carbon::setLocale(self::LOCALE);
        setlocale(LC_TIME, 'ar_EG.UTF-8', 'ar_EG', 'ar');

        return $next($request);
    }
}
