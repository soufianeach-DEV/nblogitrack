<?php

namespace App\Providers;

use App\Listeners\JournaliserAuthentification;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);
        Gate::define('view-all-orders', fn (User $user) => $user->isStaff());

        Gate::define('plan-orders', fn (User $user) => $user->isPlanner() || $user->isAdmin());

        Gate::define('manage-fleet', fn (User $user) => $user->isAdmin());

        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        Gate::define('view-logs', fn (User $user) => $user->isAdmin());

        Gate::define('validate-clients', fn (User $user) => $user->isAdmin());

        Event::subscribe(JournaliserAuthentification::class);
    }
}
