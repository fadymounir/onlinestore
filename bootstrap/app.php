<?php

use App\Http\Middleware\SetArabicLocale;
use App\Http\Middleware\ShareStorefrontData;
use App\Http\Middleware\StorefrontGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Arabic-only: runs before every request, panel and storefront alike.
        $middleware->prepend(SetArabicLocale::class);

        // Storefront header/footer data. Scoped to the web group so the
        // Filament panel (its own middleware stack) never pays for it.
        $middleware->web(append: [
            StorefrontGate::class,
            ShareStorefrontData::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
