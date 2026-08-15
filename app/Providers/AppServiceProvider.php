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

        // Toutes les pages portent la langue dans leur adresse. Hors requete
        // HTTP il n'y a pas d'URL a lire : une commande console ou un mail
        // envoye depuis la file d'attente n'aurait rien pour remplir le
        // parametre, et route() leverait une exception. Le francais sert de
        // valeur de depart, DefinirLangue la remplace a chaque requete.
        URL::defaults(['langue' => 'fr']);

        // Le suivi se consulte sans compte, avec un numero et un code : la
        // limite serree y empeche d'essayer des numeros au hasard. Un client
        // identifie ne court pas ce risque, il ne voit que ses expeditions,
        // et dix consultations par minute le bloquaient des qu'il parcourait
        // sa liste. La limite suit donc qui demande, pas ce qui est demande.
        RateLimiter::for('suivi', fn (Request $r) => $r->user()
            ? Limit::perMinute(120)->by('u'.$r->user()->id)
            : Limit::perMinute(10)->by($r->ip()));

        // Chaque expedition affichee sur la carte demande son trace et ses
        // peages : une liste de dix en consomme vingt d'un coup.
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
