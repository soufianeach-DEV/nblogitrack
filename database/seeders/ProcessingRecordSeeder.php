<?php

namespace Database\Seeders;

use App\Models\ProcessingRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProcessingRecordSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::where('role', 'ADMIN')->first();

        foreach ($this->traitements() as $rang => $t) {
            ProcessingRecord::updateOrCreate(
                ['nom' => $t['nom']],
                [...$t, 'rang' => $rang, 'updated_by' => $auteur?->id],
            );
        }
    }

    /** @return array<int, array<string, string>> */
    private function traitements(): array
    {
        $securite = 'Accès nominatif par rôle, mots de passe hachés, journalisation des actions sensibles, transport chiffré, connexion chiffrée à la base de données. Aucune sauvegarde n\'est encore programmée pour la version en ligne actuelle, hébergée sur des offres gratuites : elle sera mise en place avec l\'hébergement de production.';
        $hebergement = 'Hébergeurs : Render (serveur) et Supabase (base de données), dans leur région de Francfort.';
        $courriels = 'Brevo, service d\'envoi des courriels de l\'application.';
        $transfertHebergement = 'Données stockées à Francfort. Render et Supabase sont établies aux États-Unis : un accès depuis les États-Unis pour le support et la maintenance reste possible ; il relève des clauses contractuelles types de la Commission prévues dans leurs conditions de traitement des données.';
        $registres = 'Service VIES de la Commission européenne et registres d\'entreprises nationaux (Belgique, France, Suisse, Norvège, Royaume-Uni, Tchéquie, Finlande, Pologne, Roumanie), qui reçoivent le seul numéro de TVA saisi.';
        $adresses = 'Services de recherche d\'adresses et de fonds de carte appelés par le navigateur (Photon de komoot, Base adresse nationale française, PDOK néerlandais, OpenFreeMap et, en secours, les serveurs de tuiles d\'OpenStreetMap), qui reçoivent le texte d\'adresse saisi ou la zone affichée et l\'adresse IP du navigateur ; serveurs Overpass d\'OpenStreetMap, interrogés par le serveur avec un nom de rue et une position arrondie.';
        $transfertsRegistres = 'Numéros de TVA suisses et britanniques transmis au registre de ce pays : décision d\'adéquation de la Commission européenne.';

        return [
            [
                'nom' => 'Gestion des comptes clients',
                'finalite' => 'Créer et tenir le compte d\'une entreprise cliente, vérifier son existence auprès des registres officiels avant d\'ouvrir l\'accès.',
                'base_legale' => 'Exécution du contrat (art. 6.1.b)',
                'personnes' => 'Personnes de contact des entreprises clientes.',
                'donnees' => 'Nom, prénom, fonction, adresse électronique, téléphone, langue, mot de passe (haché), numéro de TVA et d\'entreprise, identifiant Peppol, secteur d\'activité, adresse de facturation, date et version d\'acceptation des conditions générales, motif d\'un éventuel refus ; adresse IP de connexion (journal d\'activité).',
                'destinataires' => $registres.' '.$adresses.' '.$courriels.' '.$hebergement,
                'conservation' => 'Tant que le compte est ouvert. À sa suppression : effacement immédiat, ou anonymisation si l\'entreprise a déjà été servie ou facturée ; l\'entreprise désinscrite s\'efface quand sa dernière pièce arrive au terme de sa conservation. Inscription refusée : six mois après la décision, puis effacement automatique. Acceptation des conditions générales : date et version conservées avec le compte.',
                'mesures' => $securite,
                'transferts' => $transfertsRegistres.' '.$transfertHebergement,
            ],
            [
                'nom' => 'Exécution des ordres de transport',
                'finalite' => 'Enregistrer une commande, affecter un véhicule et un conducteur, suivre l\'avancement jusqu\'à la livraison.',
                'base_legale' => 'Exécution du contrat (art. 6.1.b)',
                'personnes' => 'Personnes de contact des clients, expéditeurs et destinataires désignés dans l\'ordre, conducteurs affectés.',
                'donnees' => 'Adresses d\'enlèvement et de livraison et leurs coordonnées géographiques, nature et poids de la marchandise, nom et téléphone de l\'expéditeur, contacts et consignes sur place, conducteur et véhicule affectés, heures d\'affectation, de prise en charge, de livraison et d\'annulation, nom du réceptionnaire et réserves, code de suivi, statuts horodatés.',
                'destinataires' => 'Serveurs de calcul d\'itinéraire fondés sur OpenStreetMap, qui reçoivent les seules coordonnées des deux points. '.$adresses.' Le client voit le nom, le téléphone, la catégorie de permis et la qualification ADR du conducteur affecté à son envoi. '.$courriels.' '.$hebergement.' Sous-traitants de transport lorsqu\'un envoi leur est confié.',
                'conservation' => 'Ordre facturé : la durée de conservation de sa facture, dont il est la pièce justificative, soit sept ans à compter du 1er janvier qui suit l\'année de la facture (article III.88 du Code de droit économique ; article 60 du Code de la TVA), puis effacement automatique avec ses suppléments, ses positions et la demande de devis dont il est issu. Ordre annulé sans frais : trois ans après l\'annulation (délai de prescription le plus long de la convention CMR, article 32), puis effacement automatique.',
                'mesures' => $securite,
                'transferts' => $transfertHebergement,
            ],
            [
                'nom' => 'Suivi de position des envois',
                'finalite' => 'Informer le client de l\'avancement de son envoi, localiser un véhicule en cas de vol ou d\'accident, réorganiser les missions retardées.',
                'base_legale' => 'Exécution du contrat et intérêt légitime (art. 6.1.b et 6.1.f)',
                'personnes' => 'Conducteurs, salariés ou indépendants, pendant leurs missions.',
                'donnees' => 'Latitude, longitude, précision annoncée, horodatage, identifiant de l\'envoi et du conducteur ; position d\'un incident signalé, avec son type, son commentaire et l\'état de la marchandise ; prise de connaissance de la note d\'information (version, date, adresse IP).',
                'destinataires' => 'Le client de l\'envoi : dernière position pendant le trajet, repères de prise en charge et de livraison. Administrateurs et planificateurs, par un courriel acheminé par Brevo, pour la position d\'un incident signalé. Les serveurs de fonds de carte reçoivent la zone affichée. '.$hebergement,
                'conservation' => 'Positions relevées en cours de route : sept jours après la livraison ou l\'annulation, trente jours après la dernière mise à jour d\'une mission restée en cours. Positions de prise en charge et de livraison : un an après la livraison ou l\'annulation (délai ordinaire de prescription de la convention CMR, article 32). Position d\'un incident signalé : douze mois, avec le journal d\'activité. Prise de connaissance de la note : jusqu\'à un an après le départ du conducteur. Effacement automatique.',
                'mesures' => 'Suivi fermé par défaut, ouvert mission par mission et journalisé. Relevé limité à la mission en cours, cadence de cinq minutes imposée au serveur. Aucun écran ne permet de consulter les positions relevées pendant le trajet d\'un conducteur ; le journal d\'activité garde l\'heure des prises en charge, livraisons et incidents, pour la sécurité et les litiges, non pour contrôler le temps de travail. Information préalable exigée avant tout relevé.',
                'transferts' => $transfertHebergement,
            ],
            [
                'nom' => 'Gestion du personnel roulant',
                'finalite' => 'Vérifier qu\'un conducteur peut prendre la route, planifier dans les limites du règlement (CE) n° 561/2006, suivre les échéances de titres.',
                'base_legale' => 'Obligation légale et exécution du contrat (art. 6.1.c et 6.1.b) ; données de santé : obligations du droit du travail (art. 9.2.b)',
                'personnes' => 'Conducteurs, salariés ou indépendants.',
                'donnees' => 'Identité, coordonnées, date de naissance, statut, dates d\'entrée et de sortie et motif de sortie, numéro et catégories de permis, dates de validité du permis, du code 95, de la carte de conducteur et du certificat ADR, date de l\'examen médical, cumul d\'heures de conduite du jour, périodes d\'indisponibilité et leur motif, prises de connaissance de la note d\'information.',
                'destinataires' => 'Administrations lorsque la loi l\'impose, notamment en matière sociale. Le client voit le nom, le téléphone, la catégorie de permis et la qualification ADR du conducteur affecté à son envoi. '.$courriels.' '.$hebergement,
                'conservation' => 'Durée de la relation de travail, puis un an (prescription des actions nées du contrat de travail, loi du 3 juillet 1978, article 15) : permis, examens, cartes, dates, coordonnées, indisponibilités et prises de connaissance de la note s\'effacent alors automatiquement. Le nom reste attaché aux dossiers de transport conservés, puis s\'efface avec le dernier.',
                'mesures' => $securite.' Les motifs « maladie » et « inaptitude médicale » (données de santé, article 9 du RGPD) et « déchéance du permis » (article 10) ne sont enregistrés que comme une catégorie ; l\'encadrement de ce dernier motif reste à valider avec un conseil juridique. Le tachygraphe n\'est pas lu.',
                'transferts' => $transfertHebergement,
            ],
            [
                'nom' => 'Facturation et recouvrement',
                'finalite' => 'Émettre les factures, produire la facture électronique structurée, encaisser les paiements, tenir la comptabilité.',
                'base_legale' => 'Obligation légale (art. 6.1.c)',
                'personnes' => 'Personnes de contact des entreprises clientes.',
                'donnees' => 'Raison sociale, adresse de facturation, numéro de TVA et identifiant Peppol figés sur la facture, adresse électronique du destinataire de la facture, montants, échéances, dates et références de paiement, session de paiement en ligne.',
                'destinataires' => 'Stripe (Stripe Payments Europe, Irlande), prestataire de paiement en ligne, pour les seules données que la transaction exige (adresse électronique, montant, référence). '.$courriels.' Point d\'accès Peppol du prestataire choisi, quand il sera raccordé. Cabinet comptable. Administration fiscale. '.$hebergement,
                'conservation' => 'Factures, avoirs et paiements : sept ans à compter du 1er janvier qui suit l\'année de la pièce (article 60 du Code de la TVA, modifié par la loi du 18 décembre 2025 ; article III.88 du Code de droit économique ; article 315 du CIR 92), puis effacement automatique.',
                'mesures' => $securite,
                'transferts' => 'Stripe appartient à un groupe établi aux États-Unis : adhésion au cadre de protection des données UE–États-Unis et clauses contractuelles types de la Commission. '.$transfertHebergement,
            ],
            [
                'nom' => 'Demandes de devis',
                'finalite' => 'Répondre à une demande de prix émanant d\'une entreprise qui n\'est pas encore cliente.',
                'base_legale' => 'Mesures précontractuelles (art. 6.1.b)',
                'personnes' => 'Personnes ayant introduit une demande ; contacts sur place (enlèvement et livraison) et client final qu\'elles désignent.',
                'donnees' => 'Nom, fonction, entreprise, adresse électronique, téléphone et portable, langue de correspondance, canal et créneau de rappel, numéro de TVA et EORI, adresse de facturation, client final, contacts et horaires sur place, description et valeur déclarée de l\'envoi, budget et échéance de réponse, pièces jointes (bons de livraison, factures, photos), date d\'acceptation de l\'information, note interne et agent qui traite la demande. Le brouillon du formulaire reste dans le navigateur du demandeur.',
                'destinataires' => $registres.' '.$adresses.' '.$courriels.' '.$hebergement,
                'conservation' => 'Deux ans à compter de la réception, pièces jointes comprises, lorsque la demande reste sans suite (devis transmis sans réponse compris) ; transformée en commande, elle suit la commande et s\'efface avec elle. Effacement automatique.',
                'mesures' => $securite,
                'transferts' => $transfertsRegistres.' '.$transfertHebergement,
            ],
            [
                'nom' => 'Journal d\'activité et sécurité',
                'finalite' => 'Tracer les actions sensibles, détecter les accès anormaux, répondre à une demande d\'audit.',
                'base_legale' => 'Intérêt légitime (art. 6.1.f)',
                'personnes' => 'Tous les utilisateurs de l\'application, et les personnes qui tentent de s\'y connecter.',
                'donnees' => 'Date, utilisateur, type d\'action, objet concerné, adresse IP ; selon l\'action, adresses électroniques (connexions, échecs, changement d\'adresse), nom du conducteur, commentaire et position d\'un incident, montants. Sessions (adresse IP et navigateur), effacées après leur expiration ; compteurs de tentatives de connexion, sous empreinte, de quelques minutes.',
                'destinataires' => 'Aucun, hors hébergeurs (Render et Supabase).',
                'conservation' => 'Douze mois, puis effacement automatique.',
                'mesures' => 'Écran du journal réservé à l\'administrateur ; planificateurs et administrateurs voient l\'historique d\'un ordre tiré du journal. Journal en écriture seule pour les autres rôles.',
                'transferts' => $transfertHebergement,
            ],
            [
                'nom' => 'Accès à l\'API des partenaires',
                'finalite' => 'Permettre à une entreprise cliente de consulter ses expéditions et d\'en déposer depuis son propre outil de gestion.',
                'base_legale' => 'Exécution du contrat (art. 6.1.b)',
                'personnes' => 'Personnes de contact des entreprises titulaires d\'une clé ou qui en demandent une, expéditeurs et destinataires des envois consultés.',
                'donnees' => 'Empreinte de la clé, permissions, adresses autorisées ; demandes d\'accès (demandeur, message, motif d\'un refus, clé chiffrée jusqu\'à son premier affichage) ; journal des appels avec méthode, chemin, code de réponse, adresse IP, durée et motif d\'un refus, refus de la limite de débit compris.',
                'destinataires' => 'Aucun. Une clé rattachée à une entreprise ne voit que les expéditions de cette entreprise. '.$courriels.' '.$hebergement,
                'conservation' => 'Journal des appels : douze mois. Clés révoquées ou expirées et demandes d\'accès traitées : douze mois. Effacement automatique.',
                'mesures' => 'Clés conservées sous forme d\'empreinte ; une clé accordée sur demande reste chiffrée jusqu\'à son affichage unique, puis s\'efface. Comparaison à temps constant, restriction par adresse IP, permissions déclarées sur chaque route, journalisation des refus, y compris ceux de la limite de débit (le premier de chaque minute par clé et adresse IP).',
                'transferts' => $transfertHebergement,
            ],
            [
                'nom' => 'Mesure d\'audience du site public',
                'finalite' => 'Connaître les pages consultées, la provenance des visites et les conversions (devis, inscriptions, simulations) pour améliorer le site.',
                'base_legale' => 'Consentement (art. 6.1.a), recueilli par le bandeau des témoins et retirable à tout moment.',
                'personnes' => 'Visiteurs non connectés du site public ayant accepté la mesure d\'audience.',
                'donnees' => 'Date et heure, page, langue, type d\'appareil, provenance de l\'arrivée (domaine d\'origine, paramètres de campagne). Aucun identifiant, aucune adresse IP, aucun témoin de suivi.',
                'destinataires' => 'Aucun. La mesure est faite par l\'application elle-même. '.$hebergement,
                'conservation' => 'Treize mois, puis effacement automatique.',
                'mesures' => 'Données agrégeables seulement, sans lien entre deux pages d\'un même visiteur ; comptes connectés et robots exclus ; une empreinte salée de l\'adresse IP sert une minute de compteur contre les envois automatisés ; consultation réservée aux administrateurs.',
                'transferts' => $transfertHebergement,
            ],
            [
                'nom' => 'Comptes du personnel',
                'finalite' => 'Donner aux planificateurs et aux administrateurs un accès nominatif à l\'application.',
                'base_legale' => 'Exécution du contrat de travail (art. 6.1.b) ; intérêt légitime pour la sécurité des accès (art. 6.1.f)',
                'personnes' => 'Administrateurs et planificateurs.',
                'donnees' => 'Nom, prénom, adresse électronique, téléphone, langue, rôle, mot de passe (haché), état du compte.',
                'destinataires' => $courriels.' '.$hebergement,
                'conservation' => 'Durée de la fonction. Un compte fermé reste attaché aux actions qu\'il a tracées (journal, suppléments, paiements enregistrés) et n\'est pas effacé automatiquement.',
                'mesures' => $securite,
                'transferts' => $transfertHebergement,
            ],
        ];
    }
}
