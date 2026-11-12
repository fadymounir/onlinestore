<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * HTTPS in production, without exception.
         *
         * The shop is a PWA: a browser registers no service worker and offers
         * no install on an insecure origin, so over plain HTTP the installed
         * app simply does not exist. Session cookies and the CSRF token want
         * the same guarantee for older reasons.
         *
         * Forcing the scheme here fixes the URLs this application generates —
         * assets, routes, the manifest link, the mailed password-reset link.
         * It does not redirect an http:// request that arrives anyway; that is
         * the web server's job, and it should be doing it in front of this.
         * Behind a load balancer that terminates TLS, set TrustProxies as
         * well, or Laravel will keep seeing http on the inside.
         */
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Arabic date/number formatting across the panel and the storefront.
        Carbon::setLocale(config('app.locale'));

        // Every $paginator->links() renders the storefront's own pager rather
        // than Laravel's stock Tailwind one. Set here, not per call site, so a
        // new listing is styled without having to remember the view name.
        // Filament's tables paginate through their own Livewire component and
        // are unaffected.
        Paginator::defaultView('pagination::shop');

        // super_admin bypasses every permission check. Returning null (not
        // false) lets the normal Gate/Spatie checks run for everyone else.
        Gate::before(function ($user, string $ability): ?bool {
            return $user instanceof Admin && $user->hasRole('super_admin') ? true : null;
        });

    }
}
