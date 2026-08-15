<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A13 : les pages que tout site marchand doit porter.
 *
 * Elles sont semees a l'etat publie et modifiables : le but de
 * l'exigence n'est pas d'avoir ces textes-la, c'est que l'administrateur
 * puisse les changer sans developpeur.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::where('role', 'ADMIN')->first();

        foreach ($this->pages() as $rang => $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                [
                    ...$page,
                    'publiee' => true,
                    'publiee_le' => now(),
                    'au_pied' => true,
                    'rang' => $rang,
                    'updated_by' => $auteur?->id,
                ],
            );
        }
    }

    /** @return array<int, array<string, string>> */
    private function pages(): array
    {
        return [
            [
                'slug' => 'mentions-legales',
                'titre_fr' => 'Mentions légales',
                'titre_nl' => 'Wettelijke vermeldingen',
                'titre_en' => 'Legal notice',
                'corps_fr' => "Éditeur du site\nNBLogiTrack SRL, Avenue du Port 86C, 1000 Bruxelles, Belgique.\nNuméro d'entreprise : BE 0123.456.789.\nTéléphone : +32 (0) 2 456 78 90 — info@nblogitrack.be\n\nHébergement\nLes données sont hébergées au sein de l'Union européenne.\n\nPropriété intellectuelle\nLes contenus de ce site, à l'exception des marques et logos appartenant à leurs titulaires respectifs, sont la propriété de NBLogiTrack SRL.\n\nResponsabilité\nLes informations tarifaires affichées par le simulateur sont indicatives et ne constituent pas une offre ferme. Seule une offre écrite engage NBLogiTrack SRL.",
                'corps_nl' => "Uitgever van de website\nNBLogiTrack BV, Havenlaan 86C, 1000 Brussel, België.\nOndernemingsnummer: BE 0123.456.789.\nTelefoon: +32 (0) 2 456 78 90 — info@nblogitrack.be\n\nHosting\nDe gegevens worden binnen de Europese Unie gehost.\n\nIntellectuele eigendom\nDe inhoud van deze website is eigendom van NBLogiTrack BV, met uitzondering van de merken en logo's van hun respectieve houders.\n\nAansprakelijkheid\nDe tarieven van de simulator zijn indicatief en vormen geen vast aanbod. Alleen een schriftelijk aanbod verbindt NBLogiTrack BV.",
                'corps_en' => "Website publisher\nNBLogiTrack SRL, Avenue du Port 86C, 1000 Brussels, Belgium.\nCompany number: BE 0123.456.789.\nPhone: +32 (0) 2 456 78 90 — info@nblogitrack.be\n\nHosting\nData is hosted within the European Union.\n\nIntellectual property\nThe contents of this website are the property of NBLogiTrack SRL, except for trademarks and logos belonging to their respective owners.\n\nLiability\nPrices shown by the simulator are indicative and do not constitute a firm offer. Only a written offer binds NBLogiTrack SRL.",
            ],
            [
                'slug' => 'confidentialite',
                'titre_fr' => 'Confidentialité (RGPD)',
                'titre_nl' => 'Privacy (AVG)',
                'titre_en' => 'Privacy (GDPR)',
                'corps_fr' => "Responsable du traitement\nNBLogiTrack SRL, Avenue du Port 86C, 1000 Bruxelles.\n\nDonnées traitées\nIdentité et coordonnées professionnelles du contact, numéro de TVA, adresses d'enlèvement et de livraison, nature et poids des marchandises, historique des expéditions et des factures.\n\nFinalités\nExécution du contrat de transport, facturation, respect des obligations comptables et sociales, sécurité de l'application.\n\nBase légale\nL'exécution du contrat pour les données d'expédition, l'obligation légale pour les données de facturation, l'intérêt légitime pour les journaux techniques.\n\nDurée de conservation\nLes pièces comptables sont conservées sept ans, conformément au droit belge. Les journaux d'activité, qui enregistrent la date, l'utilisateur, l'action et l'adresse IP, sont conservés douze mois.\n\nVos droits\nAccès, rectification, effacement, limitation, portabilité et opposition. Écrivez à info@nblogitrack.be. Vous pouvez introduire une réclamation auprès de l'Autorité de protection des données.",
                'corps_nl' => "Verwerkingsverantwoordelijke\nNBLogiTrack BV, Havenlaan 86C, 1000 Brussel.\n\nVerwerkte gegevens\nIdentiteit en professionele contactgegevens, btw-nummer, ophaal- en leveradressen, aard en gewicht van de goederen, geschiedenis van zendingen en facturen.\n\nDoeleinden\nUitvoering van de vervoersovereenkomst, facturatie, naleving van boekhoudkundige en sociale verplichtingen, beveiliging van de toepassing.\n\nRechtsgrond\nDe uitvoering van de overeenkomst voor zendinggegevens, de wettelijke verplichting voor factuurgegevens, het gerechtvaardigd belang voor technische logboeken.\n\nBewaartermijn\nBoekhoudkundige stukken worden zeven jaar bewaard, conform het Belgisch recht. Activiteitenlogboeken, die datum, gebruiker, actie en IP-adres registreren, worden twaalf maanden bewaard.\n\nUw rechten\nInzage, verbetering, wissing, beperking, overdraagbaarheid en bezwaar. Schrijf naar info@nblogitrack.be. U kunt klacht indienen bij de Gegevensbeschermingsautoriteit.",
                'corps_en' => "Data controller\nNBLogiTrack SRL, Avenue du Port 86C, 1000 Brussels.\n\nData processed\nIdentity and professional contact details, VAT number, pickup and delivery addresses, nature and weight of goods, shipment and invoice history.\n\nPurposes\nPerformance of the transport contract, invoicing, compliance with accounting and social obligations, application security.\n\nLegal basis\nPerformance of the contract for shipment data, legal obligation for invoicing data, legitimate interest for technical logs.\n\nRetention\nAccounting records are kept for seven years under Belgian law. Activity logs, which record the date, user, action and IP address, are kept for twelve months.\n\nYour rights\nAccess, rectification, erasure, restriction, portability and objection. Write to info@nblogitrack.be. You may lodge a complaint with the Belgian Data Protection Authority.",
            ],
            [
                'slug' => 'conditions-generales',
                'titre_fr' => 'Conditions générales',
                'titre_nl' => 'Algemene voorwaarden',
                'titre_en' => 'Terms and conditions',
                'corps_fr' => "Champ d'application\nLes présentes conditions régissent les transports exécutés par NBLogiTrack SRL pour le compte d'entreprises. Elles ne s'adressent pas aux consommateurs.\n\nFormation du contrat\nUne réservation vaut commande dès sa confirmation par NBLogiTrack. Le prix affiché par le simulateur est indicatif tant qu'aucune offre écrite n'a été émise.\n\nTransport de marchandises\nLes transports internationaux sont soumis à la Convention CMR. Les transports nationaux sont soumis au droit belge.\n\nMatières dangereuses\nLe transport ADR fait l'objet d'une acceptation préalable et n'est confié qu'à des conducteurs titulaires du certificat correspondant.\n\nPaiement\nLes factures sont payables à trente jours de date de facture, sauf convention contraire. Tout retard fait courir les intérêts prévus par la loi du 2 août 2002 concernant la lutte contre le retard de paiement.\n\nResponsabilité\nLa responsabilité du transporteur est limitée conformément à la CMR pour le transport international.",
                'corps_nl' => "Toepassingsgebied\nDeze voorwaarden gelden voor vervoer uitgevoerd door NBLogiTrack BV voor ondernemingen. Zij richten zich niet tot consumenten.\n\nTotstandkoming\nEen boeking geldt als bestelling zodra NBLogiTrack ze bevestigt. De prijs van de simulator is indicatief zolang geen schriftelijk aanbod is uitgebracht.\n\nGoederenvervoer\nInternationaal vervoer valt onder het CMR-Verdrag. Nationaal vervoer valt onder het Belgisch recht.\n\nGevaarlijke stoffen\nADR-vervoer wordt vooraf aanvaard en enkel toevertrouwd aan chauffeurs met het overeenkomstige certificaat.\n\nBetaling\nFacturen zijn betaalbaar binnen dertig dagen na factuurdatum, behoudens andersluidende afspraak. Bij laattijdige betaling lopen de intresten voorzien in de wet van 2 augustus 2002 betreffende de bestrijding van de betalingsachterstand.\n\nAansprakelijkheid\nDe aansprakelijkheid van de vervoerder is beperkt overeenkomstig het CMR-Verdrag voor internationaal vervoer.",
                'corps_en' => "Scope\nThese terms govern transport carried out by NBLogiTrack SRL for businesses. They are not addressed to consumers.\n\nFormation of the contract\nA booking becomes an order once confirmed by NBLogiTrack. The price shown by the simulator is indicative until a written offer has been issued.\n\nCarriage of goods\nInternational carriage is governed by the CMR Convention. National carriage is governed by Belgian law.\n\nDangerous goods\nADR transport is subject to prior acceptance and is entrusted only to drivers holding the corresponding certificate.\n\nPayment\nInvoices are payable within thirty days of the invoice date unless otherwise agreed. Late payment triggers the interest provided for by the Belgian Act of 2 August 2002 on combating late payment.\n\nLiability\nThe carrier's liability is limited in accordance with the CMR Convention for international carriage.",
            ],
        ];
    }
}
