<?php

namespace App\Providers;

use App\Listeners\JournaliserAuthentification;
use App\Models\User;
use App\Support\MemoireRequete;
use App\Support\Traductions;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une instance par requete : rien ne passe d'une requete a l'autre.
        $this->app->scoped(MemoireRequete::class);
    }

    public function boot(): void
    {
        // Une remise fret retour hors bornes vendrait a perte ou n'aurait
        // aucun sens : l'application refuse de demarrer.
        $remise = config('fret.retour.remise');

        if (! is_numeric($remise) || $remise < 0 || $remise > 0.25) {
            throw new \InvalidArgumentException('fret.retour.remise doit etre comprise entre 0 et 0,25 (valeur : '.var_export($remise, true).').');
        }

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
                    // Jamais connecte : c'est une invitation, pas une
                    // reinitialisation.
                    'invitation' => $destinataire->email_verified_at === null,
                    'minutes' => config('auth.passwords.users.expire', 60),
                    'lien' => route('password.reset', [
                        'langue' => $langue,
                        'token' => $jeton,
                        'email' => $destinataire->email,
                    ]),
                ]);
        });

        // Seule une recherche (numero + code) compte : ouvrir la page ou y
        // revenir n'use pas le quota qui protege les codes contre la force
        // brute.
        RateLimiter::for('suivi', fn (Request $r) => match (true) {
            $r->user() !== null => Limit::perMinute(120)->by('u'.$r->user()->id),
            ! $r->filled('code') => Limit::none(),
            default => Limit::perMinute(10)->by($r->ip()),
        });

        // Registres d'entreprises et serveurs de numeros de rue : services
        // publics qui bloquent l'adresse du serveur s'il abuse. Une limite
        // par visiteur, et une limite commune a tous les visiteurs.
        RateLimiter::for('tva', fn (Request $r) => [
            Limit::perMinute(20)->by('ip'.$r->ip()),
            Limit::perMinute(300)->by('tous'),
        ]);
        RateLimiter::for('geo-numeros', fn (Request $r) => [
            Limit::perMinute(15)->by('ip'.$r->ip()),
            Limit::perMinute(120)->by('tous'),
        ]);

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
        Gate::define('manage-company', fn (User $user) => $user->gereEntreprise());

        Event::subscribe(JournaliserAuthentification::class);

        // Recherche « contient », insensible a la casse et aux accents ;
        // % et _ tapes par l'utilisateur se cherchent tels quels.
        $contient = function (string $colonne, string $terme, string $booleen = 'and') {
            $motif = '%'.addcslashes($terme, '\\%_').'%';

            return $this->whereRaw('unaccent(('.$this->getGrammar()->wrap($colonne).')::text) ILIKE unaccent(?)', [$motif], $booleen);
        };
        QueryBuilder::macro('whereContient', $contient);
        QueryBuilder::macro('orWhereContient', fn (string $colonne, string $terme) => $this->whereContient($colonne, $terme, 'or'));

        // Apres chaque migration, le dictionnaire suit le code : les textes
        // ajoutes depuis le dernier deploiement sont traduits tout de suite.
        Event::listen(MigrationsEnded::class, function (MigrationsEnded $evenement) {
            if ($evenement->method === 'up' && ! $this->app->runningUnitTests() && Schema::hasTable('translations')) {
                Artisan::call('traductions:synchroniser');
            }
        });
    }
}
