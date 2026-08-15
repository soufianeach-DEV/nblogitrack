<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * Le dictionnaire de depart.
 *
 * Il couvre le parcours du client, pas le back-office. Un donneur
 * d'ordre neerlandophone doit lire l'accueil, les tarifs, la commande,
 * le suivi et ses factures dans sa langue. Le personnel de NBLogiTrack
 * est francophone et travaille en francais : traduire ses ecrans
 * couterait des centaines de cles pour personne. Ce choix est assume et
 * justifie dans le document de defense.
 */
class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::TEXTES as $groupe => $cles) {
            foreach ($cles as $cle => [$fr, $nl, $en]) {
                Translation::updateOrCreate(
                    ['cle' => $groupe.'.'.$cle],
                    ['groupe' => $groupe, 'fr' => $fr, 'nl' => $nl, 'en' => $en],
                );
            }
        }
    }

    /** @var array<string, array<string, array{string, string, string}>> */
    private const TEXTES = [
        'nav' => [
            'services' => ['Services', 'Diensten', 'Services'],
            'tarifs' => ['Tarifs', 'Tarieven', 'Rates'],
            'a_propos' => ['À propos', 'Over ons', 'About'],
            'contact' => ['Contact', 'Contact', 'Contact'],
            'connexion' => ['Se connecter', 'Aanmelden', 'Sign in'],
            'inscription' => ['Créer un compte', 'Account aanmaken', 'Create account'],
            'devis' => ['Demander un devis', 'Offerte aanvragen', 'Request a quote'],
            'suivi' => ['Suivre un envoi', 'Zending volgen', 'Track a shipment'],
            'tableau_de_bord' => ['Tableau de bord', 'Dashboard', 'Dashboard'],
            'mes_expeditions' => ['Mes expéditions', 'Mijn zendingen', 'My shipments'],
            'mes_factures' => ['Mes factures', 'Mijn facturen', 'My invoices'],
            'profil' => ['Mon profil', 'Mijn profiel', 'My profile'],
            'deconnexion' => ['Déconnexion', 'Afmelden', 'Sign out'],
            'langue' => ['Langue', 'Taal', 'Language'],
        ],

        'accueil' => [
            'baseline' => [
                'Optimisez votre logistique B2B en toute confiance.',
                'Optimaliseer uw B2B-logistiek met een gerust hart.',
                'Optimise your B2B logistics with confidence.',
            ],
            'sous_titre' => [
                'La plateforme de référence pour le suivi d\'expéditions et la gestion de flotte en Belgique.',
                'Hét platform voor zendingopvolging en wagenparkbeheer in België.',
                'The reference platform for shipment tracking and fleet management in Belgium.',
            ],
            'appel_action' => ['Prêt à optimiser vos flux ?', 'Klaar om uw stromen te optimaliseren?', 'Ready to optimise your flows?'],
            'expeditions_an' => ['Expéditions / an', 'Zendingen / jaar', 'Shipments / year'],
            'fiabilite' => ['Fiabilité', 'Betrouwbaarheid', 'Reliability'],
            'pays' => ['Belgique — Union européenne', 'België — Europese Unie', 'Belgium — European Union'],
        ],

        'statut' => [
            'en_attente' => ['En attente', 'In afwachting', 'Pending'],
            'en_cours' => ['En cours', 'Onderweg', 'In transit'],
            'livre' => ['Livré', 'Geleverd', 'Delivered'],
            'annule' => ['Annulé', 'Geannuleerd', 'Cancelled'],
            'payee' => ['Payée', 'Betaald', 'Paid'],
            'envoyee' => ['Envoyée', 'Verzonden', 'Sent'],
            'echue' => ['Échue', 'Vervallen', 'Overdue'],
        ],

        'priorite' => [
            'basse' => ['Basse', 'Laag', 'Low'],
            'normale' => ['Normale', 'Normaal', 'Normal'],
            'haute' => ['Haute', 'Hoog', 'High'],
            'urgente' => ['Urgente', 'Dringend', 'Urgent'],
        ],

        'commande' => [
            'titre' => ['Nouvelle expédition', 'Nieuwe zending', 'New shipment'],
            'enlevement' => ['Adresse d\'enlèvement', 'Ophaaladres', 'Pickup address'],
            'livraison' => ['Adresse de livraison', 'Leveringsadres', 'Delivery address'],
            'date_enlevement' => ['Date d\'enlèvement', 'Ophaaldatum', 'Pickup date'],
            'date_livraison' => ['Date de livraison souhaitée', 'Gewenste leverdatum', 'Requested delivery date'],
            'poids' => ['Poids', 'Gewicht', 'Weight'],
            'volume' => ['Volume', 'Volume', 'Volume'],
            'marchandise' => ['Type de marchandise', 'Soort goederen', 'Type of goods'],
            'dangereuse' => ['Matière dangereuse (ADR)', 'Gevaarlijke stoffen (ADR)', 'Dangerous goods (ADR)'],
            'hayon' => ['Hayon élévateur nécessaire', 'Laadklep vereist', 'Tail lift required'],
            'instructions' => ['Instructions particulières', 'Bijzondere instructies', 'Special instructions'],
            'estimation' => ['Prix estimé', 'Geschatte prijs', 'Estimated price'],
            'valider' => ['Valider la commande', 'Bestelling bevestigen', 'Confirm order'],
        ],

        'suivi' => [
            'titre' => ['Suivre un envoi', 'Een zending volgen', 'Track a shipment'],
            'reference' => ['Numéro de suivi', 'Volgnummer', 'Tracking number'],
            'rechercher' => ['Rechercher', 'Zoeken', 'Search'],
            'introuvable' => [
                'Aucun envoi ne correspond à ce numéro.',
                'Geen zending gevonden met dit nummer.',
                'No shipment matches this number.',
            ],
            'arrivee_prevue' => ['Arrivée prévue', 'Verwachte aankomst', 'Expected arrival'],
            'derniere_position' => ['Dernière position connue', 'Laatst bekende positie', 'Last known position'],
        ],

        'facture' => [
            'titre' => ['Facture', 'Factuur', 'Invoice'],
            'numero' => ['Numéro', 'Nummer', 'Number'],
            'echeance' => ['Échéance', 'Vervaldatum', 'Due date'],
            'montant' => ['Montant', 'Bedrag', 'Amount'],
            'hors_tva' => ['Hors TVA', 'Excl. btw', 'Excl. VAT'],
            'tva' => ['TVA', 'Btw', 'VAT'],
            'total' => ['Total', 'Totaal', 'Total'],
            'payer' => ['Payer', 'Betalen', 'Pay'],
            'telecharger' => ['Télécharger le PDF', 'Pdf downloaden', 'Download PDF'],
            'autoliquidation' => [
                'Autoliquidation — article 21 §2 de la directive TVA',
                'Verlegging van heffing — artikel 21 §2 btw-richtlijn',
                'Reverse charge — Article 21(2) of the VAT Directive',
            ],
        ],

        'action' => [
            'enregistrer' => ['Enregistrer', 'Opslaan', 'Save'],
            'annuler' => ['Annuler', 'Annuleren', 'Cancel'],
            'voir' => ['Voir', 'Bekijken', 'View'],
            'voir_tout' => ['Voir tout', 'Alles bekijken', 'View all'],
            'retour' => ['Retour', 'Terug', 'Back'],
            'rechercher' => ['Rechercher', 'Zoeken', 'Search'],
            'aucun_resultat' => ['Aucun résultat.', 'Geen resultaten.', 'No results.'],
        ],

        'compte' => [
            'email' => ['E-mail professionnel', 'Professioneel e-mailadres', 'Business email'],
            'mot_de_passe' => ['Mot de passe', 'Wachtwoord', 'Password'],
            'oublie' => ['Oublié ?', 'Vergeten?', 'Forgot?'],
            'se_souvenir' => ['Se souvenir de moi', 'Onthoud mij', 'Remember me'],
            'bon_retour' => ['Bon retour parmi nous', 'Welkom terug', 'Welcome back'],
            'entreprise' => ['Entreprise', 'Onderneming', 'Company'],
            'numero_tva' => ['Numéro de TVA', 'Btw-nummer', 'VAT number'],
            'attente_validation' => [
                'Votre entreprise doit être validée par nos équipes avant votre première connexion.',
                'Uw onderneming moet door onze diensten worden goedgekeurd voor uw eerste aanmelding.',
                'Your company must be approved by our team before your first sign-in.',
            ],
        ],
    ];
}
