<?php

namespace Database\Seeders;

use App\Models\ProcessingRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Le registre des traitements de l'article 30.
 *
 * Les entrees decrivent ce que l'application fait reellement. Un
 * registre qui enumere des traitements plausibles mais absents ne
 * protege personne : au premier controle, l'ecart se voit.
 */
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
        $securite = 'Accès nominatif par rôle, mots de passe hachés, journalisation des actions sensibles, sauvegardes chiffrées dans l\'Union européenne.';
        $aucunTransfert = 'Aucun transfert hors de l\'Union européenne.';

        return [
            [
                'nom' => 'Gestion des comptes clients',
                'finalite' => 'Créer et tenir le compte d\'une entreprise cliente, vérifier son existence auprès des registres officiels avant d\'ouvrir l\'accès.',
                'base_legale' => 'Exécution du contrat (art. 6.1.b)',
                'personnes' => 'Personnes de contact des entreprises clientes.',
                'donnees' => 'Nom, prénom, fonction, adresse électronique, téléphone, langue, numéro de TVA de l\'entreprise, adresse de facturation.',
                'destinataires' => 'Service VIES de la Commission européenne et registres d\'entreprises belge et français, pour la seule vérification du numéro de TVA.',
                'conservation' => 'Durée de la relation commerciale, puis les délais de prescription applicables.',
                'mesures' => $securite,
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Exécution des ordres de transport',
                'finalite' => 'Enregistrer une commande, planifier la tournée, affecter un véhicule et un conducteur, suivre l\'avancement jusqu\'à la livraison.',
                'base_legale' => 'Exécution du contrat (art. 6.1.b)',
                'personnes' => 'Personnes de contact des clients, expéditeurs et destinataires désignés dans l\'ordre, conducteurs affectés.',
                'donnees' => 'Adresses d\'enlèvement et de livraison et leurs coordonnées géographiques, nature et poids de la marchandise, contacts sur place, dates, statuts horodatés.',
                'destinataires' => 'Service de calcul d\'itinéraire fondé sur OpenStreetMap, qui reçoit les seules coordonnées des deux points. Sous-traitants de transport lorsqu\'un envoi leur est confié.',
                'conservation' => 'Cinq ans, durée de conservation des documents de transport.',
                'mesures' => $securite,
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Suivi de position des envois',
                'finalite' => 'Informer le client de l\'avancement de son envoi, localiser un véhicule en cas de vol ou d\'accident, replanifier une tournée retardée.',
                'base_legale' => 'Exécution du contrat et intérêt légitime (art. 6.1.b et 6.1.f)',
                'personnes' => 'Conducteurs salariés pendant leur service.',
                'donnees' => 'Latitude, longitude, précision annoncée, horodatage, identifiant de l\'envoi et du conducteur.',
                'destinataires' => 'Aucun. Les positions ne sortent pas de l\'application.',
                'conservation' => 'Positions relevées en cours de route : sept jours après la livraison, effacement automatique. Positions de prise en charge et de livraison : durée du dossier de transport.',
                'mesures' => 'Suivi fermé par défaut, ouvert mission par mission et journalisé. Relevé limité à la mission en cours, cadence de cinq minutes imposée au serveur. Aucun écran ne permet de consulter les déplacements d\'un conducteur. Information préalable exigée avant tout relevé.',
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Gestion du personnel roulant',
                'finalite' => 'Vérifier qu\'un conducteur peut prendre la route, planifier dans les limites du règlement (CE) n° 561/2006, suivre les échéances de titres.',
                'base_legale' => 'Obligation légale et exécution du contrat (art. 6.1.c et 6.1.b)',
                'personnes' => 'Conducteurs salariés.',
                'donnees' => 'Identité, coordonnées, numéro et catégories de permis, dates de validité, certificat ADR, date de l\'examen médical, échéance de la carte de conducteur, cumul d\'heures de conduite du jour.',
                'destinataires' => 'Administrations lorsque la loi l\'impose, notamment en matière sociale.',
                'conservation' => 'Durée de la relation de travail, puis les délais de prescription sociale.',
                'mesures' => $securite.' Le tachygraphe n\'est pas lu.',
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Facturation et recouvrement',
                'finalite' => 'Émettre les factures, produire la facture électronique structurée, encaisser les paiements, tenir la comptabilité.',
                'base_legale' => 'Obligation légale (art. 6.1.c)',
                'personnes' => 'Personnes de contact des entreprises clientes.',
                'donnees' => 'Raison sociale, adresse de facturation, numéro de TVA, identifiant Peppol, montants, échéances, dates de paiement.',
                'destinataires' => 'Prestataire de paiement en ligne, pour les seules données que la transaction exige. Cabinet comptable. Administration fiscale.',
                'conservation' => 'Sept ans, conformément au Code de la TVA.',
                'mesures' => $securite,
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Demandes de devis',
                'finalite' => 'Répondre à une demande de prix émanant d\'une entreprise qui n\'est pas encore cliente.',
                'base_legale' => 'Mesures précontractuelles (art. 6.1.b)',
                'personnes' => 'Personnes ayant introduit une demande.',
                'donnees' => 'Nom, entreprise, adresse électronique, téléphone, numéro de TVA, description de l\'envoi envisagé.',
                'destinataires' => 'Aucun.',
                'conservation' => 'Deux ans lorsque la demande reste sans suite.',
                'mesures' => $securite,
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Journal d\'activité et sécurité',
                'finalite' => 'Tracer les actions sensibles, détecter les accès anormaux, répondre à une demande d\'audit.',
                'base_legale' => 'Intérêt légitime (art. 6.1.f)',
                'personnes' => 'Tous les utilisateurs de l\'application.',
                'donnees' => 'Date, utilisateur, type d\'action, objet concerné, adresse IP.',
                'destinataires' => 'Aucun.',
                'conservation' => 'Douze mois.',
                'mesures' => 'Consultation réservée à l\'administrateur. Journal en écriture seule pour les autres rôles.',
                'transferts' => $aucunTransfert,
            ],
            [
                'nom' => 'Accès à l\'API des partenaires',
                'finalite' => 'Permettre à une entreprise cliente de consulter ses expéditions et d\'en déposer depuis son propre outil de gestion.',
                'base_legale' => 'Exécution du contrat (art. 6.1.b)',
                'personnes' => 'Personnes de contact des entreprises titulaires d\'une clé, expéditeurs et destinataires des envois consultés.',
                'donnees' => 'Empreinte de la clé, permissions, adresses autorisées, journal des appels avec méthode, chemin, code de réponse, adresse IP et durée.',
                'destinataires' => 'Aucun. Une clé rattachée à une entreprise ne voit que les expéditions de cette entreprise.',
                'conservation' => 'Journal des appels : douze mois. Clés : jusqu\'à leur révocation.',
                'mesures' => 'Clés jamais conservées en clair, comparaison à temps constant, restriction par adresse IP, permissions déclarées sur chaque route, journalisation des refus.',
                'transferts' => $aucunTransfert,
            ],
        ];
    }
}
