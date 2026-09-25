<?php

namespace App\Providers;

use App\Listeners\JournaliserAuthentification;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Le lien de mot de passe, qui sert aussi d'invitation au personnel,
        // part dans la langue du destinataire et a la charte des autres
        // courriels, plutot que dans le gabarit anglais du cadriciel.
        ResetPassword::toMailUsing(function (User $destinataire, string $jeton) {
            $langue = $destinataire->preferredLocale();

            return (new MailMessage)
                ->subject(Traductions::t('courriel.mdp_sujet', 'Choisissez votre mot de passe NBLogiTrack'))
                ->view('emails.lien-mot-de-passe', [
                    'destinataire' => $destinataire,
                    'minutes' => config('auth.passwords.users.expire', 60),
                    'lien' => route('password.reset', [
                        'langue' => $langue,
                        'token' => $jeton,
                        'email' => $destinataire->email,
                    ]),
                ]);
        });

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
