<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
