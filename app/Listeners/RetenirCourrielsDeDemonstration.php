<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Retient les courriels adresses aux domaines inventes du jeu de
 * demonstration (config mail.domaines_bloques). Ces adresses n'existent
 * pas : chaque envoi reviendrait en erreur chez le prestataire, qui
 * suspend un compte dont le taux d'erreurs monte, et consommerait le
 * quota quotidien. Les vraies adresses partent normalement, y compris
 * dans un courriel qui compte aussi une adresse de demonstration.
 */
class RetenirCourrielsDeDemonstration
{
    /** Faux quand plus aucun destinataire ne reste : l'envoi n'a pas lieu. */
    public function handle(MessageSending $evenement): ?bool
    {
        $motifs = (array) config('mail.domaines_bloques', []);

        if ($motifs === []) {
            return null;
        }

        $message = $evenement->message;
        $retenues = [];

        foreach (['To', 'Cc', 'Bcc'] as $champ) {
            $adresses = $message->{'get'.$champ}();
            $gardees = array_filter($adresses, function (Address $adresse) use ($motifs, &$retenues) {
                if (self::bloquee($adresse->getAddress(), $motifs)) {
                    $retenues[] = $adresse->getAddress();

                    return false;
                }

                return true;
            });

            if (count($gardees) !== count($adresses)) {
                $message->{strtolower($champ)}(...array_values($gardees));
            }
        }

        if ($retenues !== []) {
            // Avertissement : la production ne garde que ce niveau et au-dela.
            Log::warning('Courriel retenu : adresse de démonstration', [
                'adresses' => $retenues,
                'sujet' => $message->getSubject(),
            ]);
        }

        // Plus aucun destinataire : l'envoi n'a pas lieu. Sinon, rien a
        // dire : les autres ecouteurs de l'envoi gardent la parole.
        return $message->getTo() === [] && $message->getCc() === [] && $message->getBcc() === []
            ? false
            : null;
    }

    /** @param  array<int, string>  $motifs */
    public static function bloquee(string $adresse, array $motifs): bool
    {
        $domaine = mb_strtolower((string) substr(strrchr($adresse, '@') ?: '', 1));

        foreach ($motifs as $motif) {
            $motif = mb_strtolower($motif);

            if (fnmatch($motif, $domaine) || str_ends_with($domaine, '.'.$motif)) {
                return true;
            }
        }

        return false;
    }
}
