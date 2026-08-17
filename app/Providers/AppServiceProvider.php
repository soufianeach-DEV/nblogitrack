<?php

namespace App\Providers;

use App\Listeners\JournaliserAuthentification;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        URL::defaults(['langue' => 'fr']);

        RateLimiter::for('suivi', fn (Request $r) => $r->user()
            ? Limit::perMinute(120)->by('u'.$r->user()->id)
            : Limit::perMinute(10)->by($r->ip()));

        RateLimiter::for('itineraires', fn (Request $r) => Limit::perMinute(240)->by('u'.$r->user()->id));

        Gate::define('view-all-orders', fn (User $user) => $user->isStaff());

        Gate::define('plan-orders', fn (User $user) => $user->isPlanner() || $user->isAdmin());

        Gate::define('manage-fleet', fn (User $user) => $user->isAdmin());

        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        Gate::define('view-logs', fn (User $user) => $user->isAdmin());

        Gate::define('handle-quotes', fn (User $user) => $user->isStaff());

        Gate::define('validate-clients', fn (User $user) => $user->isAdmin());
        Gate::define('control-payments', fn (User $user) => $user->isAdmin());
        Gate::define('view-fleet', fn (User $user) => $user->isStaff());
        Gate::define('drive', fn (User $user) => $user->isDriver());

        Event::subscribe(JournaliserAuthentification::class);
    }
}
