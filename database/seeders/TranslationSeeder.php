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
            'expertise' => ['Expertise logistique belge', 'Belgische logistieke expertise', 'Belgian logistics expertise'],
            'titre' => ['Gérez vos transports', 'Beheer uw transporten', 'Manage your transport'],
            'titre_suite' => ['en toute simplicité.', 'eenvoudig en helder.', 'with complete simplicity.'],
            'accroche' => [
                'Plateforme dédiée aux professionnels belges et européens. Optimisez vos flux, maîtrisez vos coûts et sécurisez vos expéditions B2B en temps réel.',
                'Platform voor Belgische en Europese professionals. Optimaliseer uw stromen, beheers uw kosten en beveilig uw B2B-zendingen in real time.',
                'A platform built for Belgian and European professionals. Optimise your flows, control your costs and secure your B2B shipments in real time.',
            ],
            'demarrer' => ['Démarrer l\'aventure', 'Aan de slag', 'Get started'],
            'mon_espace' => ['Mon espace', 'Mijn omgeving', 'My workspace'],

            'services_titre' => [
                'Une solution complète pour votre flotte',
                'Een complete oplossing voor uw wagenpark',
                'A complete solution for your fleet',
            ],
            'services_texte' => [
                'Concentrez-vous sur votre cœur de métier, nous nous occupons de l\'intelligence logistique.',
                'Concentreer u op uw kernactiviteit, wij zorgen voor de logistieke intelligentie.',
                'Focus on your core business, we handle the logistics intelligence.',
            ],
            'service_reservation' => ['Réservation de transport', 'Transport boeken', 'Transport booking'],
            'service_reservation_texte' => [
                'Interface guidée pour commander vos trajets en quelques clics. Adresses vérifiées, formule adaptée au délai et prix connu avant validation.',
                'Begeleide interface om uw ritten in enkele klikken te bestellen. Gecontroleerde adressen, formule afgestemd op de termijn en prijs gekend voor bevestiging.',
                'A guided interface to order your trips in a few clicks. Verified addresses, a formula matched to your deadline, and the price known before you confirm.',
            ],
            'service_suivi' => ['Suivi en temps réel', 'Realtime opvolging', 'Real-time tracking'],
            'service_suivi_texte' => [
                'Chaque expédition reçoit un numéro de suivi et un code d\'accès. Le destinataire consulte l\'état de la livraison sans avoir de compte.',
                'Elke zending krijgt een volgnummer en een toegangscode. De bestemmeling raadpleegt de status van de levering zonder account.',
                'Every shipment gets a tracking number and an access code. The consignee checks the delivery status without an account.',
            ],
            'service_facturation' => ['Facturation simplifiée', 'Vereenvoudigde facturatie', 'Simplified invoicing'],
            'service_facturation_texte' => [
                'Identifiant Peppol généré automatiquement à l\'inscription, pour une facturation électronique conforme dans toute l\'Union européenne.',
                'Peppol-identificatie automatisch aangemaakt bij registratie, voor conforme elektronische facturatie in de hele Europese Unie.',
                'A Peppol identifier generated automatically at sign-up, for compliant electronic invoicing across the European Union.',
            ],

            'tarifs_titre' => ['Une tarification transparente', 'Transparante tarifering', 'Transparent pricing'],
            'tarifs_texte' => [
                'Pas de coût caché. Le prix se calcule sur la distance routière réelle, le carburant, les péages du pays traversé et le poids transporté.',
                'Geen verborgen kosten. De prijs wordt berekend op de werkelijke wegafstand, de brandstof, de tol van het doorkruiste land en het vervoerde gewicht.',
                'No hidden costs. The price is calculated from the actual road distance, fuel, the tolls of the country crossed and the weight carried.',
            ],
            'tarif_economique' => ['Économique — 5 jours et plus', 'Economisch — vanaf 5 dagen', 'Economy — 5 days or more'],
            'tarif_economique_texte' => [
                'Groupage sur les axes réguliers, pour les envois non urgents.',
                'Groepage op de vaste assen, voor niet-dringende zendingen.',
                'Groupage on regular routes, for non-urgent shipments.',
            ],
            'tarif_standard' => ['Standard — 3 jours', 'Standaard — 3 dagen', 'Standard — 3 days'],
            'tarif_standard_texte' => [
                'Le meilleur rapport entre délai et coût pour un envoi courant.',
                'De beste verhouding tussen termijn en kost voor een gewone zending.',
                'The best balance of time and cost for an everyday shipment.',
            ],
            'tarif_express' => ['Express — 48 heures', 'Express — 48 uur', 'Express — 48 hours'],
            'tarif_express_texte' => [
                'Transport dédié, facturé au coût de revient réel plus marge.',
                'Toegewijd transport, gefactureerd aan de werkelijke kostprijs plus marge.',
                'Dedicated transport, invoiced at actual cost price plus margin.',
            ],
            'calculer' => ['Calculer mon tarif', 'Mijn tarief berekenen', 'Calculate my rate'],

            'appel_action' => ['Prêt à optimiser vos flux ?', 'Klaar om uw stromen te optimaliseren?', 'Ready to optimise your flows?'],
            'appel_action_texte' => [
                'L\'inscription se fait avec votre numéro de TVA. Vos informations sont reprises des registres officiels européens.',
                'De registratie verloopt via uw btw-nummer. Uw gegevens worden overgenomen uit de officiële Europese registers.',
                'Registration uses your VAT number. Your details are taken from the official European registers.',
            ],

            'apropos_titre' => [
                'L\'excellence logistique au service de l\'industrie belge',
                'Logistieke uitmuntendheid ten dienste van de Belgische industrie',
                'Logistics excellence serving Belgian industry',
            ],
            'apropos_texte' => [
                'NBLogiTrack s\'adresse aux entreprises qui expédient régulièrement en Belgique et dans l\'Union européenne. Marchandise palettisée, transport dédié ou groupage, matières dangereuses sous certification ADR. Chaque société cliente est vérifiée auprès du registre européen de la TVA avant d\'obtenir un accès.',
                'NBLogiTrack richt zich tot ondernemingen die regelmatig verzenden in België en in de Europese Unie. Gepalletiseerde goederen, toegewijd transport of groepage, gevaarlijke stoffen onder ADR-certificering. Elke klant wordt gecontroleerd in het Europese btw-register voor toegang wordt verleend.',
                'NBLogiTrack serves companies shipping regularly within Belgium and the European Union. Palletised goods, dedicated transport or groupage, dangerous goods under ADR certification. Every client company is verified against the European VAT register before being granted access.',
            ],
            'inscrire' => ['Inscrire mon entreprise', 'Mijn onderneming registreren', 'Register my company'],

            'titre_page' => ['Transport et logistique B2B', 'B2B-transport en logistiek', 'B2B transport and logistics'],
            'certif_cmr' => ['e-CMR certifié', 'e-CMR gecertificeerd', 'e-CMR certified'],
            'pied_signature' => [
                'L\'excellence logistique au service de l\'industrie belge. Précision, fiabilité, innovation.',
                'Logistieke uitmuntendheid ten dienste van de Belgische industrie. Precisie, betrouwbaarheid, innovatie.',
                'Logistics excellence serving Belgian industry. Precision, reliability, innovation.',
            ],
            'pied_legal' => ['Légal', 'Juridisch', 'Legal'],
            'pied_routier' => ['Transport routier', 'Wegtransport', 'Road transport'],
            'pied_groupage' => ['Groupage européen', 'Europese groepage', 'European groupage'],
            'pied_dedie' => ['Transport dédié', 'Toegewijd transport', 'Dedicated transport'],
            'pied_adr' => ['Matières dangereuses', 'Gevaarlijke stoffen', 'Dangerous goods'],
            'pied_mentions' => ['Mentions légales', 'Wettelijke vermeldingen', 'Legal notice'],
            'pied_rgpd' => ['Confidentialité (RGPD)', 'Privacy (AVG)', 'Privacy (GDPR)'],
            'pied_conditions' => ['Conditions générales', 'Algemene voorwaarden', 'Terms and conditions'],
            'droits' => ['Tous droits réservés.', 'Alle rechten voorbehouden.', 'All rights reserved.'],
            'pays' => ['Belgique — Union européenne', 'België — Europese Unie', 'Belgium — European Union'],
        ],

        'vitrine' => [
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
            'expeditions_an' => ['Expéditions / an', 'Zendingen / jaar', 'Shipments / year'],
            'fiabilite' => ['Fiabilité', 'Betrouwbaarheid', 'Reliability'],
        ],

        'auth' => [
            'connexion' => ['Connexion', 'Aanmelden', 'Sign in'],
            'inscription' => ['Inscription', 'Registreren', 'Register'],
            'identifiants' => [
                'Veuillez entrer vos identifiants pour accéder à votre tableau de bord.',
                'Voer uw gegevens in om toegang te krijgen tot uw dashboard.',
                'Enter your credentials to access your dashboard.',
            ],
            'aide' => ['Aide · Confidentialité', 'Hulp · Privacy', 'Help · Privacy'],
            'mdp_oublie' => ['Mot de passe oublié', 'Wachtwoord vergeten', 'Forgotten password'],
            'mdp_oublie_texte' => [
                'Indiquez l\'adresse électronique de votre compte : nous vous envoyons un lien qui vous permettra d\'en choisir un nouveau.',
                'Geef het e-mailadres van uw account op: wij sturen u een link waarmee u een nieuw wachtwoord kunt kiezen.',
                'Enter your account\'s email address: we will send you a link to choose a new password.',
            ],
            'envoyer_lien' => ['Envoyer le lien', 'Link versturen', 'Send the link'],
            'nouveau_mdp' => ['Nouveau mot de passe', 'Nieuw wachtwoord', 'New password'],
            'adresse_email' => ['Adresse électronique', 'E-mailadres', 'Email address'],
            'confirmation_mdp' => ['Confirmation du mot de passe', 'Bevestiging van het wachtwoord', 'Confirm password'],
            'enregistrer_mdp' => ['Enregistrer le mot de passe', 'Wachtwoord opslaan', 'Save password'],
            'verif_titre' => ['Vérification de l\'adresse', 'Verificatie van het adres', 'Email verification'],
            'verif_texte' => [
                'Merci pour votre inscription. Confirmez votre adresse électronique en cliquant sur le lien que nous venons de vous envoyer. Si vous ne l\'avez pas reçu, nous vous en envoyons un nouveau.',
                'Bedankt voor uw registratie. Bevestig uw e-mailadres door op de link te klikken die wij u zonet hebben gestuurd. Niet ontvangen? Dan sturen wij u een nieuwe.',
                'Thank you for registering. Confirm your email address by clicking the link we just sent you. If you did not receive it, we will send you a new one.',
            ],
            'verif_renvoye' => [
                'Un nouveau lien de vérification vient d\'être envoyé à l\'adresse indiquée lors de votre inscription.',
                'Er is zonet een nieuwe verificatielink verstuurd naar het adres dat u bij uw registratie opgaf.',
                'A new verification link has just been sent to the address you provided during registration.',
            ],
            'renvoyer_lien' => ['Renvoyer le lien', 'Link opnieuw versturen', 'Resend the link'],
            'confirm_titre' => ['Confirmation du mot de passe', 'Wachtwoordbevestiging', 'Password confirmation'],
            'confirm_texte' => [
                'Cette partie de l\'application est protégée. Confirmez votre mot de passe pour continuer.',
                'Dit deel van de applicatie is beveiligd. Bevestig uw wachtwoord om verder te gaan.',
                'This part of the application is protected. Confirm your password to continue.',
            ],
            'confirmer' => ['Confirmer', 'Bevestigen', 'Confirm'],
            'inscrire_titre' => ['Inscrire votre entreprise', 'Uw onderneming registreren', 'Register your company'],
            'activation' => [
                'Votre compte sera activé après vérification de votre entreprise par nos services.',
                'Uw account wordt geactiveerd na controle van uw onderneming door onze diensten.',
                'Your account will be activated once our team has verified your company.',
            ],
            'tva_format' => [
                'Saisis le numéro complet, code pays inclus (ex. BE0123456789).',
                'Voer het volledige nummer in, landcode inbegrepen (bv. BE0123456789).',
                'Enter the full number, country code included (e.g. BE0123456789).',
            ],
            'tva_exemple' => [
                'ex. BE0123456789 ou SIRET 34119222700013',
                'bv. BE0123456789 of SIRET 34119222700013',
                'e.g. BE0123456789 or SIRET 34119222700013',
            ],
            'verifier' => ['Vérifier', 'Controleren', 'Verify'],
            'verification' => ['Vérification…', 'Controle…', 'Verifying…'],
            'vies_indisponible' => [
                'Le service européen VIES est momentanément injoignable.',
                'De Europese VIES-dienst is tijdelijk onbereikbaar.',
                'The European VIES service is temporarily unavailable.',
            ],
            'situation_refus' => [
                'Situation juridique : :libelle. L\'inscription ne peut pas être acceptée.',
                'Juridische toestand: :libelle. De registratie kan niet worden aanvaard.',
                'Legal status: :libelle. The registration cannot be accepted.',
            ],
            'tva_actif' => ['Numéro actif', 'Actief nummer', 'Active number'],
            'tva_identifie' => [
                ', entreprise identifiée dans le registre européen VIES.',
                ', onderneming geïdentificeerd in het Europese VIES-register.',
                ', company identified in the European VIES register.',
            ],
            'dirigeant_repris' => [
                'Dirigeant repris du registre national : vérifiez ou remplacez-le.',
                'Bestuurder overgenomen uit het nationale register: controleer of vervang deze.',
                'Director taken from the national register: check or replace it.',
            ],
            'raison_sociale' => ['Raison sociale', 'Handelsnaam', 'Company name'],
            'raison_sociale_ex' => ['ex. Transports Dupont SA', 'bv. Transport Peeters NV', 'e.g. Dupont Transport Ltd'],
            'secteur' => ['Secteur d\'activité', 'Activiteitensector', 'Business sector'],
            'secteur_ex' => ['ex. Construction', 'bv. Bouw', 'e.g. Construction'],
            'adresse_siege' => ['Adresse du siège', 'Adres van de zetel', 'Registered office address'],
            'adresse_officielle' => [
                'Adresse officielle du registre VIES',
                'Officieel adres uit het VIES-register',
                'Official address from the VIES register',
            ],
            'autre_adresse' => ['Saisir une autre adresse', 'Een ander adres invoeren', 'Enter a different address'],
            'peppol' => ['Identifiant Peppol :', 'Peppol-identificatie:', 'Peppol identifier:'],
            'peppol_attente' => [
                'déduit après vérification du numéro',
                'afgeleid na controle van het nummer',
                'derived after the number is verified',
            ],
            'personne_contact' => ['Personne de contact', 'Contactpersoon', 'Contact person'],
            'prenom' => ['Prénom', 'Voornaam', 'First name'],
            'nom' => ['Nom', 'Naam', 'Last name'],
            'fonction' => ['Fonction', 'Functie', 'Position'],
            'fonction_ex' => ['ex. Directeur logistique', 'bv. Logistiek directeur', 'e.g. Logistics manager'],
            'telephone' => ['Téléphone', 'Telefoon', 'Phone'],
            'identifiants_titre' => ['Identifiants', 'Aanmeldgegevens', 'Credentials'],
            'confirmer_mdp' => ['Confirmer le mot de passe', 'Wachtwoord bevestigen', 'Confirm password'],
            'marque' => [
                'Je certifie que cette dénomination sociale ne porte pas atteinte à une marque déposée.',
                'Ik verklaar dat deze handelsnaam geen inbreuk maakt op een gedeponeerd merk.',
                'I certify that this company name does not infringe any registered trademark.',
            ],
            'envoyer_demande' => ['Envoyer la demande', 'Aanvraag versturen', 'Submit the request'],
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
