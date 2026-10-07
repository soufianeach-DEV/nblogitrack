<?php

namespace App\Support;

use App\Http\Controllers\ActivityLogController;
use App\Models\ActivityLog;
use Illuminate\Support\Carbon;

/**
 * Le journal garde sa phrase d'origine, en francais : c'est la trace qui
 * fait foi. A l'affichage, un ecran neerlandais ou anglais lit le libelle
 * traduit de l'action suivi des references citees (numeros de suivi, de
 * facture, adresses), et le detail avec des libelles et des valeurs
 * lisibles au lieu des codes techniques.
 */
class JournalLisible
{
    /** Codes techniques rencontres dans le detail, avec leur libelle. */
    public const CODES = [
        'PENDING' => ['statut.en_attente', 'En attente'],
        'ASSIGNED' => ['statut.affecte', 'Affecté'],
        'IN_PROGRESS' => ['statut.en_cours', 'En cours'],
        'DELIVERED' => ['statut.livre', 'Livré'],
        'CANCELLED' => ['statut.annule', 'Annulé'],
        'DRAFT' => ['facture.brouillon', 'Brouillon'],
        'SENT' => ['statut.envoyee', 'Envoyée'],
        'PAID' => ['statut.payee', 'Payée'],
        'OVERDUE' => ['statut.en_retard', 'En retard'],
        'CREDITED' => ['facture.annulee_avoir', 'Annulée par avoir'],
        'CLIENT' => ['roles.client', 'Client'],
        'PLANNER' => ['roles.planner', 'Planificateur'],
        'ADMIN' => ['roles.admin', 'Administrateur'],
        'DRIVER' => ['roles.driver', 'Chauffeur'],
        'TRANSFER' => ['journal.methode_virement', 'Virement'],
        'OTHER' => ['journal.methode_autre', 'Autre'],
        'STRIPE' => ['journal.methode_carte', 'Carte (Stripe)'],
        'COMPTE_DESACTIVE' => ['journal.motif_compte_desactive', 'Compte désactivé'],
        'INSCRIPTION_REFUSEE' => ['journal.motif_inscription_refusee', 'Inscription refusée'],
        'ENTREPRISE_EN_ATTENTE' => ['journal.motif_entreprise_en_attente', 'Entreprise en attente de validation'],
        'ECRAN_CONFIRMATION' => ['journal.ecran_confirmation', 'Confirmation du mot de passe'],
        'ECRAN_MOT_DE_PASSE' => ['journal.ecran_mot_de_passe', 'Changement du mot de passe'],
        'ECRAN_ADRESSE' => ['journal.ecran_adresse', 'Changement d\'adresse e-mail'],
        'ECRAN_SUPPRESSION' => ['journal.ecran_suppression', 'Suppression du compte'],
    ];

    /** Libelles des cles de detail les plus frequentes. */
    private const CLES = [
        'statut' => 'Statut',
        'ancien_statut' => 'Ancien statut',
        'nouveau_statut' => 'Nouveau statut',
        'role' => 'Rôle',
        'methode' => 'Moyen de paiement',
        'montant' => 'Montant',
        'motif' => 'Motif',
        'vehicule' => 'Véhicule',
        'chauffeur' => 'Chauffeur',
        'chauffeur_id' => 'Fiche chauffeur',
        'ancien_camion' => 'Ancien camion',
        'ancien_chauffeur_id' => 'Ancienne fiche chauffeur',
        'enlevement' => 'Enlèvement',
        'ancien_enlevement' => 'Ancien enlèvement',
        'entreprise' => 'Entreprise',
        'email' => 'Adresse e-mail',
        'factures' => 'Factures',
        'is_available' => 'Disponible',
        'concerne' => 'Concerne',
        'frais' => 'Frais',
        'reference' => 'Référence',
        'version' => 'Version',
        'ip' => 'Adresse IP',
        'requete' => 'Requête',
        'ecran' => 'Écran',
    ];

    public static function resume(ActivityLog $ligne): string
    {
        if (app()->getLocale() === 'fr' || $ligne->description === null) {
            return (string) $ligne->description;
        }

        $libelle = Traductions::t(
            'journal.action_'.str_replace('.', '_', $ligne->action),
            ActivityLogController::ACTIONS[$ligne->action] ?? $ligne->action,
        );

        preg_match_all(
            '/\b(?:TRK|FAC|AV|DEV)-\d{4}-\d{3,6}\b|[\w.+-]+@[\w-]+(?:\.[\w-]+)+|\b\d-[A-Z]{3}-\d{3}\b/u',
            $ligne->description,
            $references,
        );

        $references = array_values(array_unique($references[0]));

        return $references === [] ? $libelle : $libelle.': '.implode(', ', $references);
    }

    /**
     * @param  array<string, mixed>|null  $proprietes
     * @return list<string>
     */
    public static function details(?array $proprietes): array
    {
        $lignes = [];
        $deuxPoints = app()->getLocale() === 'fr' ? ' : ' : ': ';

        foreach ($proprietes ?? [] as $cle => $valeur) {
            if ($valeur === null || $valeur === '' || $valeur === []) {
                continue;
            }

            $libelle = Traductions::t('journal.prop_'.$cle, self::CLES[$cle] ?? ucfirst(str_replace('_', ' ', (string) $cle)));
            $lignes[] = $libelle.$deuxPoints.self::valeur($valeur);
        }

        return $lignes;
    }

    private static function valeur(mixed $valeur): string
    {
        if (is_bool($valeur)) {
            return $valeur ? Traductions::t('journal.oui', 'Oui') : Traductions::t('journal.non', 'Non');
        }

        if (is_array($valeur)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? self::valeur($v) : json_encode($v, JSON_UNESCAPED_UNICODE), $valeur));
        }

        $texte = (string) $valeur;

        // « PENDING → ASSIGNED » : chaque code traduit.
        if (str_contains($texte, '→')) {
            return implode(' → ', array_map(fn ($p) => self::valeur(trim($p)), explode('→', $texte)));
        }

        if (isset(self::CODES[$texte])) {
            return Traductions::t(...self::CODES[$texte]);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $texte)) {
            return Carbon::parse($texte)->setTimezone(config('app.timezone'))->format('d/m/Y H:i');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte)) {
            return Carbon::parse($texte)->format('d/m/Y');
        }

        return $texte;
    }
}
