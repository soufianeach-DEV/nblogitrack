<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $auteur = User::where('role', 'ADMIN')->first();

        foreach ($this->pages() as $rang => $page) {
            $interne = $page['interne'] ?? false;
            unset($page['interne']);

            Page::updateOrCreate(
                ['slug' => $page['slug']],
                [
                    ...$page,
                    'publiee' => ! $interne,
                    'publiee_le' => $interne ? null : now(),
                    'au_pied' => ! $interne,
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
                'corps_fr' => $this->mentionsFr(),
                'corps_nl' => $this->mentionsNl(),
                'corps_en' => $this->mentionsEn(),
            ],
            [
                'slug' => 'confidentialite',
                'titre_fr' => 'Politique de confidentialité',
                'titre_nl' => 'Privacybeleid',
                'titre_en' => 'Privacy policy',
                'corps_fr' => $this->viePriveeFr(),
                'corps_nl' => $this->viePriveeNl(),
                'corps_en' => $this->viePriveeEn(),
            ],
            [
                'slug' => 'politique-cookies',
                'titre_fr' => 'Politique de cookies',
                'titre_nl' => 'Cookiebeleid',
                'titre_en' => 'Cookie policy',
                'corps_fr' => $this->cookiesFr(),
                'corps_nl' => $this->cookiesNl(),
                'corps_en' => $this->cookiesEn(),
            ],
            [
                'slug' => 'conditions-generales',
                'titre_fr' => 'Conditions générales de transport',
                'titre_nl' => 'Algemene vervoersvoorwaarden',
                'titre_en' => 'General conditions of carriage',
                'corps_fr' => $this->conditionsFr(),
                'corps_nl' => $this->conditionsNl(),
                'corps_en' => $this->conditionsEn(),
            ],
            [
                'slug' => 'information-chauffeurs',
                'interne' => true,
                'titre_fr' => 'Note d\'information aux conducteurs',
                'titre_nl' => 'Informatienota voor chauffeurs',
                'titre_en' => 'Information notice for drivers',
                'corps_fr' => $this->noteFr(),
                'corps_nl' => $this->noteNl(),
                'corps_en' => $this->noteEn(),
            ],
        ];
    }

    private function noteFr(): string
    {
        return <<<'TXT'
Cette note vous informe du traitement de vos données dans NBLogiTrack. Elle vous est remise avant tout relevé de position, comme la loi l'exige.

Ce qui est relevé
Quand vous déclarez la prise en charge puis la livraison d'un envoi, votre position est enregistrée à cet instant. Deux points par mission. Ils accompagnent le dossier de transport, au même titre qu'une mention sur une lettre de voiture.

Pour certaines missions seulement, un planificateur peut ouvrir un suivi de position pendant le trajet. Dans ce cas, et dans ce cas uniquement, votre position est relevée toutes les cinq minutes au plus. Un bandeau visible sur votre écran vous l'indique pendant toute la durée du partage.

Si vous signalez un accident, une panne ou un dommage depuis l'application, la position de votre téléphone à ce moment est jointe au signalement. Elle est gardée au journal de la mission et envoyée par courriel à l'administration et à la planification, pour organiser l'aide.

Ce qui n'est pas relevé
Votre position n'est relevée qu'aux moments décrits ci-dessus, jamais en dehors d'une mission qui vous est affectée. Aucun boîtier n'est installé dans les véhicules. Votre vitesse n'est pas mesurée. Votre carte de conducteur n'est pas lue. Vos déplacements privés ne concernent pas l'entreprise et ne sont pas enregistrés.

À quoi cela sert
À informer le client de l'avancement de son envoi, à retrouver un véhicule en cas de vol ou d'accident, et à réorganiser les missions quand un retard compromet la suite.

Ce à quoi cela ne sert pas
Ces données ne servent pas à vous évaluer, à vous sanctionner, à vous noter, ni à contrôler votre temps de travail. Aucun écran de l'application ne permet de consulter les positions relevées pendant vos trajets. Le journal d'activité, réservé à l'administrateur, garde l'heure de vos prises en charge, livraisons et signalements, pour la sécurité et le traitement des litiges.

Combien de temps
Les positions relevées pendant un trajet sont effacées automatiquement dans les sept jours suivant la livraison. Les deux points de prise en charge et de livraison s'effacent un an après la livraison. La position d'un signalement reste au journal douze mois.

Ce que voit le client
Le client dont vous transportez l'envoi voit votre nom, votre numéro de téléphone, votre catégorie de permis et votre qualification ADR. Il voit aussi les deux points de prise en charge et de livraison avec leur heure et, si le suivi est ouvert, votre dernière position connue.

Vos droits
Vous pouvez demander l'accès aux données qui vous concernent, leur rectification ou leur effacement, et vous opposer au traitement. Écrivez à info@nblogitrack.be. Vous pouvez également introduire une réclamation auprès de l'Autorité de protection des données, Rue de la Presse 35, 1000 Bruxelles.

Ce que vous déclarez
En validant, vous attestez avoir pris connaissance de cette note ; la date, la version de la note et l'adresse IP de votre validation sont enregistrées. Vous ne donnez pas votre accord : le traitement repose sur l'exécution de votre contrat de travail, ou de votre contrat de prestation si vous êtes indépendant, et sur l'intérêt légitime de l'entreprise, non sur votre consentement. Votre refus de valider n'aurait donc aucun effet sur vos droits, mais aucun relevé de position ne serait effectué tant que vous n'auriez pas été informé.
TXT;
    }

    private function noteNl(): string
    {
        return <<<'TXT'
Deze nota informeert u over de verwerking van uw gegevens in NBLogiTrack. Zij wordt u overhandigd vóór elke positieopname, zoals de wet vereist.

Wat wordt opgenomen
Wanneer u de ophaling en vervolgens de levering van een zending aangeeft, wordt uw positie op dat ogenblik geregistreerd. Twee punten per opdracht. Zij horen bij het vervoersdossier, net als een vermelding op een vrachtbrief.

Enkel voor bepaalde opdrachten kan een planner een positieopvolging openen tijdens de rit. In dat geval, en enkel dan, wordt uw positie hoogstens om de vijf minuten opgenomen. Een zichtbare banner op uw scherm meldt dit zolang het delen duurt.

Als u via de toepassing een ongeval, een panne of schade meldt, wordt de positie van uw telefoon op dat ogenblik bij de melding gevoegd. Zij wordt in het logboek van de opdracht bewaard en per e-mail naar de administratie en de planning gestuurd, om de hulp te organiseren.

Wat niet wordt opgenomen
Uw positie wordt enkel opgenomen op de hierboven beschreven ogenblikken, nooit buiten een opdracht die u is toegewezen. Er wordt geen kastje in de voertuigen geplaatst. Uw snelheid wordt niet gemeten. Uw bestuurderskaart wordt niet uitgelezen. Uw privéverplaatsingen gaan de onderneming niet aan en worden niet geregistreerd.

Waarvoor het dient
Om de klant over de voortgang van zijn zending te informeren, om een voertuig terug te vinden bij diefstal of ongeval, en om de opdrachten te herschikken wanneer vertraging het vervolg in het gedrang brengt.

Waarvoor het niet dient
Deze gegevens dienen niet om u te beoordelen, te sanctioneren, te quoteren, noch om uw arbeidstijd te controleren. Geen enkel scherm van de toepassing laat toe de tijdens uw ritten opgenomen posities te raadplegen. Het activiteitenlogboek, voorbehouden aan de beheerder, bewaart het tijdstip van uw ophalingen, leveringen en meldingen, voor de beveiliging en de afhandeling van geschillen.

Hoelang
Posities die tijdens een rit zijn opgenomen, worden automatisch gewist binnen de zeven dagen na de levering. De twee punten van ophaling en levering worden een jaar na de levering gewist. De positie van een melding blijft twaalf maanden in het logboek.

Wat de klant ziet
De klant van wie u de zending vervoert, ziet uw naam, uw telefoonnummer, uw rijbewijscategorie en uw ADR-bevoegdheid. Hij ziet ook de twee punten van ophaling en levering met hun tijdstip en, als de opvolging geopend is, uw laatst gekende positie.

Uw rechten
U kunt inzage vragen in de gegevens die u betreffen, hun verbetering of wissing, en bezwaar maken tegen de verwerking. Schrijf naar info@nblogitrack.be. U kunt ook klacht indienen bij de Gegevensbeschermingsautoriteit, Drukpersstraat 35, 1000 Brussel.

Wat u verklaart
Door te bevestigen verklaart u kennis te hebben genomen van deze nota; de datum, de versie van de nota en het IP-adres van uw bevestiging worden geregistreerd. U geeft geen toestemming: de verwerking steunt op de uitvoering van uw arbeidsovereenkomst, of van uw dienstverleningsovereenkomst als u zelfstandige bent, en op het gerechtvaardigd belang van de onderneming, niet op uw toestemming. Uw weigering te bevestigen zou dus geen gevolgen hebben voor uw rechten, maar er zou geen enkele positieopname gebeuren zolang u niet geïnformeerd bent.
TXT;
    }

    private function noteEn(): string
    {
        return <<<'TXT'
This notice informs you about the processing of your data in NBLogiTrack. It is given to you before any position is recorded, as the law requires.

What is recorded
When you declare the pickup and then the delivery of a consignment, your position is recorded at that moment. Two points per mission. They accompany the transport file, just like an entry on a consignment note.

For certain missions only, a planner may open position tracking during the journey. In that case, and only then, your position is recorded every five minutes at most. A visible banner on your screen tells you so for as long as sharing lasts.

If you report an accident, a breakdown or damage through the application, your phone's position at that moment is attached to the report. It is kept in the mission's log and sent by email to the administration and planning staff, to organise assistance.

What is not recorded
Your position is recorded only at the moments described above, never outside a mission assigned to you. No unit is fitted in the vehicles. Your speed is not measured. Your driver card is not read. Your private journeys are no concern of the company and are not recorded.

What it is for
To inform the customer of the progress of their consignment, to locate a vehicle in the event of theft or accident, and to reorganise missions when a delay jeopardises what follows.

What it is not for
This data is not used to assess you, sanction you, rate you, or monitor your working time. No screen in the application allows the positions recorded during your journeys to be consulted. The activity log, reserved for the administrator, keeps the time of your pickups, deliveries and reports, for security and dispute handling.

For how long
Positions recorded during a journey are erased automatically within seven days of delivery. The two pickup and delivery points are erased one year after delivery. The position of a report stays in the log for twelve months.

What the customer sees
The customer whose consignment you carry sees your name, your phone number, your licence category and your ADR qualification. They also see the two pickup and delivery points with their time and, if tracking is open, your latest known position.

Your rights
You may request access to the data concerning you, its rectification or erasure, and object to the processing. Write to info@nblogitrack.be. You may also lodge a complaint with the Belgian Data Protection Authority, Rue de la Presse 35, 1000 Brussels.

What you are declaring
By confirming, you certify that you have read this notice; the date, the version of the notice and the IP address of your confirmation are recorded. You are not giving your consent: the processing rests on the performance of your employment contract, or of your service contract if you are self-employed, and on the company's legitimate interest, not on your consent. Refusing to confirm would therefore have no effect on your rights, but no position would be recorded for as long as you had not been informed.
TXT;
    }

    private function mentionsFr(): string
    {
        return <<<'TXT'
## Éditeur du site
NBLogiTrack SRL, société à responsabilité limitée de droit belge.
Siège social : Avenue du Port 86C, 1000 Bruxelles, Belgique.
Numéro d'entreprise et numéro de TVA : BE 0123.456.749.
Registre des personnes morales de Bruxelles, section francophone.
Téléphone : +32 (0) 2 456 78 90 — Courriel : info@nblogitrack.be

## Licence de transport
NBLogiTrack SRL exerce l'activité de transport de marchandises par route pour compte de tiers sous licence communautaire délivrée par le Service public fédéral Mobilité et Transports, conformément au règlement (CE) n° 1072/2009 et à la loi du 15 juillet 2013 relative au transport de marchandises par route.

## Assurances
- Responsabilité civile exploitation.
- Responsabilité du transporteur (CMR), couvrant la responsabilité découlant de la Convention de Genève du 19 mai 1956.
- L'assurance de la marchandise elle-même, dite assurance facultés, n'est pas comprise. Le donneur d'ordre qui souhaite couvrir la valeur pleine de son envoi souscrit sa propre police ou demande une déclaration de valeur, dans les conditions prévues aux conditions générales.

## Responsable de la publication
La direction de NBLogiTrack SRL, joignable à l'adresse du siège social.

## Hébergement
L'application est hébergée par Render (Render Services, Inc.), dans sa région de Francfort (Allemagne). La base de données est hébergée par Supabase (Supabase, Inc.), dans sa région de Francfort. Les données restent stockées dans l'Union européenne.

## Propriété intellectuelle
La structure du site, ses textes, sa charte graphique, ses illustrations et son code sont protégés par le droit d'auteur. Toute reproduction, représentation, adaptation ou extraction, totale ou partielle, par quelque procédé que ce soit, est interdite sans autorisation écrite préalable.

Les marques, dénominations et logos de tiers cités demeurent la propriété de leurs titulaires respectifs et ne sont mentionnés qu'à titre d'identification.

## Liens hypertextes
Les liens vers des sites tiers sont proposés pour la commodité de l'utilisateur. NBLogiTrack SRL n'exerce aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu, leur disponibilité et leurs pratiques en matière de données.

## Valeur des informations publiées
Les prix affichés par le simulateur tarifaire sont indicatifs. Ils reposent sur une distance calculée automatiquement et sur des hypothèses de chargement standard, et ne tiennent pas compte des sujétions particulières d'un envoi. Ils ne constituent ni une offre au sens de l'article 5.16 du Code civil, ni un engagement contractuel. Seule une offre écrite émise par NBLogiTrack SRL engage la société.

## Disponibilité du service
NBLogiTrack SRL met en œuvre les moyens raisonnables pour assurer l'accessibilité du site et de l'espace client. Elle se réserve la faculté d'en interrompre l'accès pour maintenance, sans que cette interruption n'ouvre droit à indemnité.

## Signalement d'un contenu
Toute personne estimant qu'un contenu publié porte atteinte à ses droits peut le signaler par écrit à info@nblogitrack.be, en identifiant précisément le contenu et le droit invoqué.

## Règlement des litiges
Les parties recherchent une solution amiable avant toute procédure. À défaut, les litiges relèvent des tribunaux visés aux conditions générales.

Le présent site s'adresse exclusivement à des professionnels : les règles propres aux litiges de consommation ne s'appliquent pas aux relations qu'il régit.

## Droit applicable
Le présent site et son utilisation sont régis par le droit belge.
TXT;
    }

    private function mentionsNl(): string
    {
        return <<<'TXT'
## Uitgever van de website
NBLogiTrack BV, besloten vennootschap naar Belgisch recht.
Maatschappelijke zetel: Havenlaan 86C, 1000 Brussel, België.
Ondernemingsnummer en btw-nummer: BE 0123.456.749.
Rechtspersonenregister Brussel, Franstalige afdeling.
Telefoon: +32 (0) 2 456 78 90 — E-mail: info@nblogitrack.be

## Vervoersvergunning
NBLogiTrack BV verricht goederenvervoer over de weg voor rekening van derden onder een communautaire vergunning afgeleverd door de Federale Overheidsdienst Mobiliteit en Vervoer, overeenkomstig verordening (EG) nr. 1072/2009 en de wet van 15 juli 2013 betreffende het goederenvervoer over de weg.

## Verzekeringen
- Burgerlijke aansprakelijkheid uitbating.
- Aansprakelijkheid van de vervoerder (CMR), voor de aansprakelijkheid die voortvloeit uit het Verdrag van Genève van 19 mei 1956.
- De verzekering van de goederen zelf is niet inbegrepen. De opdrachtgever die de volle waarde van zijn zending wil dekken, sluit zijn eigen polis af of vraagt een waardeaangifte, onder de voorwaarden van de algemene voorwaarden.

## Verantwoordelijke uitgever
De directie van NBLogiTrack BV, bereikbaar op het adres van de maatschappelijke zetel.

## Hosting
De toepassing wordt gehost door Render (Render Services, Inc.), in zijn regio Frankfurt (Duitsland). De databank wordt gehost door Supabase (Supabase, Inc.), in zijn regio Frankfurt. De gegevens blijven opgeslagen in de Europese Unie.

## Intellectuele eigendom
De structuur van de website, de teksten, de huisstijl, de illustraties en de broncode zijn auteursrechtelijk beschermd. Elke reproductie, weergave, aanpassing of ontlening, geheel of gedeeltelijk en op welke wijze ook, is verboden zonder voorafgaande schriftelijke toestemming.

Vermelde merken, benamingen en logo's van derden blijven eigendom van hun respectieve houders en worden enkel ter identificatie genoemd.

## Hyperlinks
Links naar websites van derden worden aangeboden voor het gemak van de gebruiker. NBLogiTrack BV heeft geen controle over die websites en wijst elke aansprakelijkheid af voor de inhoud, de beschikbaarheid en het gegevensbeleid ervan.

## Waarde van de gepubliceerde informatie
De prijzen van de tariefsimulator zijn indicatief. Zij berusten op een automatisch berekende afstand en op standaardaannames inzake belading, en houden geen rekening met de bijzonderheden van een zending. Zij vormen geen aanbod in de zin van artikel 5.16 van het Burgerlijk Wetboek, noch een contractuele verbintenis. Alleen een schriftelijk aanbod van NBLogiTrack BV verbindt de vennootschap.

## Beschikbaarheid van de dienst
NBLogiTrack BV stelt alles redelijkerwijs in het werk om de website en de klantenzone toegankelijk te houden. Zij behoudt zich het recht voor de toegang te onderbreken voor onderhoud, zonder dat die onderbreking recht geeft op schadevergoeding.

## Melding van inhoud
Wie meent dat gepubliceerde inhoud zijn rechten schendt, kan dit schriftelijk melden op info@nblogitrack.be, met nauwkeurige aanduiding van de inhoud en het ingeroepen recht.

## Geschillenregeling
Partijen zoeken eerst een minnelijke oplossing. Bij gebreke daarvan zijn de rechtbanken bevoegd die in de algemene voorwaarden zijn aangeduid.

Deze website richt zich uitsluitend tot professionelen: de regels voor consumentengeschillen zijn niet van toepassing op de betrekkingen die hij regelt.

## Toepasselijk recht
Deze website en het gebruik ervan worden beheerst door het Belgisch recht.
TXT;
    }

    private function mentionsEn(): string
    {
        return <<<'TXT'
## Website publisher
NBLogiTrack SRL, a limited liability company under Belgian law.
Registered office: Avenue du Port 86C, 1000 Brussels, Belgium.
Company and VAT number: BE 0123.456.749.
Register of Legal Entities, Brussels, French-speaking division.
Phone: +32 (0) 2 456 78 90 — Email: info@nblogitrack.be

## Transport licence
NBLogiTrack SRL carries goods by road for hire and reward under a Community licence issued by the Belgian Federal Public Service Mobility and Transport, in accordance with Regulation (EC) No 1072/2009 and the Belgian Act of 15 July 2013 on the carriage of goods by road.

## Insurance
- General public liability.
- Carrier's liability (CMR), covering liability arising from the Geneva Convention of 19 May 1956.
- Insurance of the goods themselves is not included. A customer wishing to cover the full value of a consignment takes out their own policy or requests a declaration of value, under the terms set out in the general conditions.

## Publication manager
The management of NBLogiTrack SRL, reachable at the registered office address.

## Hosting
The application is hosted by Render (Render Services, Inc.) in its Frankfurt region (Germany). The database is hosted by Supabase (Supabase, Inc.) in its Frankfurt region. The data remains stored in the European Union.

## Intellectual property
The structure of this website, its texts, visual identity, illustrations and source code are protected by copyright. Any reproduction, communication, adaptation or extraction, in whole or in part and by any means, is prohibited without prior written consent.

Third-party trademarks, names and logos remain the property of their respective owners and are mentioned for identification purposes only.

## Hyperlinks
Links to third-party websites are provided for the user's convenience. NBLogiTrack SRL exercises no control over those websites and accepts no liability for their content, availability or data practices.

## Status of published information
Prices shown by the rate simulator are indicative. They rely on an automatically calculated distance and on standard loading assumptions, and do not account for the specific constraints of a consignment. They constitute neither an offer within the meaning of Article 5.16 of the Belgian Civil Code nor a contractual commitment. Only a written offer issued by NBLogiTrack SRL binds the company.

## Service availability
NBLogiTrack SRL takes reasonable steps to keep the website and the customer area accessible. It reserves the right to interrupt access for maintenance, without such interruption giving rise to compensation.

## Reporting content
Anyone who considers that published content infringes their rights may report it in writing to info@nblogitrack.be, precisely identifying the content and the right invoked.

## Dispute resolution
The parties shall seek an amicable solution before any proceedings. Failing that, disputes fall to the courts designated in the general conditions.

This website is addressed exclusively to professionals: the rules specific to consumer disputes do not apply to the relationships it governs.

## Governing law
This website and its use are governed by Belgian law.
TXT;
    }

    private function viePriveeFr(): string
    {
        return <<<'TXT'
## Responsable du traitement
NBLogiTrack SRL, Avenue du Port 86C, 1000 Bruxelles, numéro d'entreprise BE 0123.456.749.
Toute question relative aux données personnelles : info@nblogitrack.be

## À qui s'adresse cette politique
Elle décrit le traitement des données de quatre catégories de personnes :
- les personnes de contact des entreprises clientes et des fournisseurs ;
- les conducteurs et le personnel d'exploitation ;
- les expéditeurs et destinataires désignés dans un ordre de transport, qui ne sont pas nécessairement nos clients ;
- les visiteurs du site.

## Données traitées
Personnes de contact : nom, fonction, adresse professionnelle, téléphone, courriel, langue, numéro de TVA de l'entreprise, historique des expéditions, factures et paiements, journaux de connexion.

Conducteurs et personnel : identité, coordonnées, date de naissance, statut (salarié ou indépendant), date d'entrée et, le cas échéant, date et motif de sortie (retraite, démission, licenciement, inaptitude médicale, déchéance du permis) ; numéro de permis et catégories ; dates de validité du permis, du code 95, de la carte de conducteur et du certificat ADR ; date de l'examen médical ; cumul d'heures de conduite de la journée ; périodes d'indisponibilité et leur motif (congé, maladie, formation) ; affectations aux missions, heures de prise en charge et de livraison et positions décrites ci-dessous ; date, version et adresse IP de la prise de connaissance de la note d'information. Les motifs « maladie » et « inaptitude médicale » sont enregistrés comme une simple catégorie.

Expéditeurs et destinataires : nom, adresse d'enlèvement ou de livraison, téléphone de contact, nom de la personne ayant réceptionné la marchandise et réserves éventuelles.

Visiteurs : l'adresse IP et l'identification du navigateur, enregistrées avec la session et effacées après son expiration (deux heures sans activité) ; le choix exprimé dans le bandeau des témoins ; avec leur accord, la mesure d'audience décrite dans la politique de cookies. Le brouillon du formulaire de demande de devis est gardé dans le navigateur du visiteur, jamais sur nos serveurs, jusqu'à l'envoi de la demande ou jusqu'à ce qu'il l'efface.

## Finalités et bases légales
- Exécuter le contrat de transport, affecter les véhicules et les conducteurs, suivre les envois — exécution du contrat.
- Établir les factures, recouvrer les créances, tenir la comptabilité — obligation légale et intérêt légitime.
- Respecter les obligations en matière de temps de conduite, de repos et de qualification des conducteurs — obligation légale.
- Établir, en dehors de l'application, les documents de transport, dont la lettre de voiture CMR, et les déclarations en douane — obligation légale.
- Assurer la sécurité de l'application, détecter les accès anormaux — intérêt légitime.
- Répondre aux demandes de devis — mesures précontractuelles.
- Mesurer la fréquentation du site public, sans identifier les visiteurs — consentement, retirable à tout moment par le lien « Gérer les cookies ».

## Suivi géolocalisé des envois
Le suivi repose sur les éléments suivants.

Les coordonnées des adresses d'enlèvement et de livraison, géocodées une fois à la création de l'ordre. Les changements de statut saisis par le conducteur, avec leur heure. Deux repères de position, relevés au moment où il déclare la prise en charge puis la livraison : ce sont des faits de gestion, gardés un an comme une mention portée sur une lettre de voiture. Enfin, lorsque le conducteur signale lui-même un accident, une panne ou un dommage, la position de ce signalement : elle est gardée au journal d'activité et envoyée par courriel à l'administration et à la planification.

## Suivi de position pendant le trajet
Pour certaines missions, et pour celles-là seulement, une position intermédiaire est relevée pendant que la marchandise roule. Cette possibilité s'ouvre mission par mission, jamais pour la flotte entière, et la décision est journalisée avec le nom de qui l'a prise.

Localiser un véhicule revient à localiser son conducteur : c'est une donnée relative à un travailleur. L'Autorité de protection des données admet un tel traitement lorsqu'il poursuit un but professionnel précis, mais juge disproportionné un contrôle permanent et systématique. Le dispositif est donc borné :

- la position ne part que depuis l'écran du conducteur, pendant une mission en cours ; il n'existe aucun boîtier qui émette en dehors du travail ;
- un bandeau visible lui indique, à l'instant même, que sa position est partagée ;
- un relevé toutes les cinq minutes au plus, cadence imposée par le serveur ;
- pendant le trajet, le client ne voit qu'un point, le dernier connu, sur l'itinéraire de son propre envoi ; il n'accède jamais à la trace du trajet. Il voit aussi, pour son envoi, les deux repères de prise en charge et de livraison avec leur heure, ainsi que le nom du conducteur, son téléphone, sa catégorie de permis et sa qualification ADR ;
- aucun écran de l'application ne permet de consulter les positions relevées pendant le trajet d'un conducteur donné. Le journal d'activité, réservé à l'administrateur, garde en revanche l'heure de chaque prise en charge, livraison et incident, avec le nom du conducteur ; il sert à la sécurité et au traitement des litiges, non au contrôle du temps de travail ;
- ces positions ne servent jamais à évaluer, sanctionner ou noter un conducteur ;
- elles sont effacées automatiquement sept jours après la livraison ou l'annulation, ou trente jours après la dernière mise à jour d'une mission restée en cours, par une tâche planifiée et non par une intervention manuelle.

Les conducteurs sont informés de ce dispositif préalablement et individuellement, par une note qui leur est adressée et dont ils accusent réception dans l'application. La date, la version de la note et l'adresse IP de cette prise de connaissance sont enregistrées ; elle conditionne le relevé : tant qu'elle n'est pas donnée, aucune position n'est enregistrée, quand bien même le suivi aurait été ouvert pour la mission. Une note réécrite doit être reprise.

Cet accusé n'est pas un consentement. Dans une relation de travail, le consentement n'est pas librement donné et ne peut fonder le traitement ; ce qui est prouvé ici est l'information préalable, non un accord.

## Ce que l'application ne fait pas
Le tachygraphe n'est pas lu. Le cumul d'heures de conduite du jour sert à planifier dans les limites du règlement (CE) n° 561/2006 ; il ne sert pas à surveiller. Les positions relevées pendant le trajet ne sont pas conservées au-delà du délai indiqué ci-dessus ; les deux repères de prise en charge et de livraison s'effacent un an après la livraison, et la position d'un incident signalé reste au journal d'activité pendant douze mois.

## Destinataires et sous-traitants
Les données ne sont ni vendues, ni louées, ni échangées. Elles sont communiquées, dans la limite du nécessaire :
- aux hébergeurs de l'application : Render (Render Services, Inc.) pour le serveur et Supabase (Supabase, Inc.) pour la base de données, dans leur région de Francfort (Allemagne) ;
- au service d'envoi de courriels Brevo, qui achemine les courriels de l'application (confirmation d'adresse, mot de passe, avis d'expédition, factures, note d'information aux conducteurs, alertes d'incident) et reçoit l'adresse du destinataire et le contenu du message ;
- au prestataire de paiement en ligne Stripe (Stripe Payments Europe, Irlande), qui reçoit l'adresse électronique du payeur, le montant et la référence de la facture ;
- aux serveurs publics de calcul d'itinéraire fondés sur OpenStreetMap (router.project-osrm.org et, en secours, routing.openstreetmap.de), qui reçoivent les coordonnées des points d'enlèvement et de livraison, sans nom ni référence de dossier ;
- aux serveurs publics Overpass d'OpenStreetMap, qui reçoivent un nom de rue et une position arrondie à un kilomètre environ, pour proposer les numéros de la rue, ou l'emprise d'un itinéraire, pour situer les péages, sans nom ni référence de dossier ;
- au service VIES de la Commission européenne et aux registres d'entreprises nationaux (Belgique, France, Suisse, Norvège, Royaume-Uni, Tchéquie, Finlande, Pologne, Roumanie), qui reçoivent le seul numéro de TVA saisi, pour vérifier l'identité d'une entreprise ;
- aux services de recherche d'adresses et de fonds de carte (Photon de komoot, Base adresse nationale française, PDOK néerlandais, OpenFreeMap et, en secours, les serveurs de tuiles d'OpenStreetMap), appelés directement par le navigateur, qui reçoivent le texte d'adresse saisi ou la zone de carte affichée, et l'adresse IP du navigateur ;
- au client, pour le conducteur affecté à son envoi : nom, téléphone, catégorie de permis et qualification ADR ;
- aux sous-traitants de transport lorsqu'un envoi leur est confié ;
- au cabinet comptable, à l'assureur et, le cas échéant, au conseil juridique ;
- aux administrations lorsque la loi l'impose, notamment en matière fiscale, douanière et sociale.

Render, Supabase, Brevo et Stripe interviennent comme sous-traitants, dans le cadre de leurs conditions de traitement des données (article 28 du RGPD). Les services publics d'itinéraire, d'adresses et de cartes sont utilisés sans compte, sous leurs propres conditions ; ils ne reçoivent ni nom ni référence de dossier.

## Transferts hors de l'Union européenne
Les données sont traitées au sein de l'Union européenne. Font exception :
- la vérification d'un numéro de TVA suisse ou britannique, transmis au registre de ce pays, qui bénéficie d'une décision d'adéquation de la Commission européenne ;
- le paiement en ligne : Stripe appartient à un groupe établi aux États-Unis, adhérent au cadre de protection des données UE–États-Unis, et lié par les clauses contractuelles types de la Commission ;
- une livraison hors de l'Union, qui exige de transmettre les données du destinataire, sur la base des garanties prévues au chapitre V du RGPD ;
- l'hébergement : Render et Supabase sont des sociétés établies aux États-Unis ; les données sont stockées à Francfort, mais un accès depuis les États-Unis reste possible pour le support et la maintenance ; il est encadré par les clauses contractuelles types de la Commission prévues dans leurs conditions de traitement des données.

## Durées de conservation
- Factures, avoirs, paiements et autres pièces comptables : sept ans à compter du 1er janvier qui suit leur année (article 60 du Code de la TVA, modifié par la loi du 18 décembre 2025 ; article III.88 du Code de droit économique), puis effacement automatique.
- Ordres de transport, avec les noms et téléphones de contact, le nom du réceptionnaire et les réserves : la durée de conservation de la facture dont ils sont la pièce justificative, puis effacement automatique ; un ordre annulé sans frais : trois ans après l'annulation.
- Positions relevées pendant le trajet : sept jours après la livraison ou l'annulation, ou trente jours après la dernière mise à jour d'une mission restée en cours ; repères de prise en charge et de livraison : un an après la livraison ou l'annulation.
- Données des conducteurs : la durée de la relation de travail. Un an après le départ, délai de prescription des actions nées du contrat de travail, les données de gestion (permis, examens, cartes, dates, coordonnées, indisponibilités, prises de connaissance de la note) sont effacées ; le nom reste attaché aux dossiers de transport conservés, puis s'efface avec le dernier.
- Journal d'activité de l'application et journal des appels de l'API, qui enregistrent notamment la date, l'utilisateur, l'action et l'adresse IP : douze mois, puis effacement automatique. Clés d'API révoquées ou expirées et demandes d'accès traitées : douze mois.
- Demandes de devis restées sans suite, pièces jointes comprises : deux ans à compter de leur réception ; transformées en commande, elles suivent la commande.
- Mesure d'audience, sans donnée permettant d'identifier un visiteur : treize mois, puis effacement automatique.
- Compte client : tant qu'il est ouvert. À sa suppression, le nom, l'adresse électronique, le téléphone et le mot de passe sont effacés ; si l'entreprise a déjà été servie ou facturée, sa fiche est conservée jusqu'à ce que sa dernière pièce arrive au terme de sa conservation. L'adresse électronique reste au journal d'activité pendant sa durée de conservation. Inscription refusée : six mois après la décision, puis effacement automatique.

## Sécurité
L'accès à l'application est nominatif et limité par le rôle de chacun. Les mots de passe ne sont jamais conservés en clair. Les échanges avec le serveur sont chiffrés. Les actions sensibles sont journalisées, et ces journaux sont effacés automatiquement passé leur durée de conservation.

La version en ligne actuelle, hébergée sur des offres gratuites, n'a pas encore de sauvegarde programmée par NBLogiTrack, et les fichiers déposés (pièces jointes des demandes de devis, documents publiés) sont stockés sur le serveur de l'application. La sauvegarde et la restauration seront mises en place avec l'hébergement de production, et cette page sera mise à jour à ce moment-là. Nous préférons l'indiquer plutôt que d'annoncer une mesure qui n'est pas encore en place.

## Décision automatisée
Aucune décision produisant des effets juridiques n'est prise sur le seul fondement d'un traitement automatisé. Le calcul tarifaire et les propositions d'affectation d'un véhicule ou d'un conducteur sont des aides à la décision : un planificateur valide.

## Témoins de connexion
Le site dépose les témoins nécessaires à son fonctionnement et à la sécurité de la session. Avec votre accord seulement, il mesure aussi sa fréquentation, par ses propres moyens : aucune adresse IP ni aucun identifiant n'est enregistré avec la mesure, et aucun service tiers n'intervient. Ce choix se modifie à tout moment par le lien « Gérer les cookies » en bas de page ; la politique de cookies en donne le détail. Aucun témoin publicitaire n'est déposé.

## Vos droits
Vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité. Ces droits s'exercent par écrit à info@nblogitrack.be. Depuis son profil, chaque utilisateur peut aussi télécharger ses données dans un fichier lisible par une autre application ; un utilisateur client peut y supprimer son compte. Une réponse est apportée dans le mois, prorogeable de deux mois si la demande est complexe.

Certains droits connaissent des limites : les données figurant sur une facture ou sur une lettre de voiture ne peuvent être effacées avant l'expiration du délai légal de conservation.

## Réclamation
Vous pouvez introduire une réclamation auprès de l'Autorité de protection des données, Rue de la Presse 35, 1000 Bruxelles — contact@apd-gba.be — sans préjudice de tout recours juridictionnel.

## Modifications
La présente politique peut être adaptée. La date de dernière mise à jour figure au bas de cette page.
TXT;
    }

    private function viePriveeNl(): string
    {
        return <<<'TXT'
## Verwerkingsverantwoordelijke
NBLogiTrack BV, Havenlaan 86C, 1000 Brussel, ondernemingsnummer BE 0123.456.749.
Vragen over persoonsgegevens: info@nblogitrack.be

## Voor wie geldt dit beleid
Het beschrijft de verwerking van gegevens van vier categorieën personen:
- contactpersonen van klanten en leveranciers;
- chauffeurs en exploitatiepersoneel;
- afzenders en geadresseerden vermeld in een vervoersopdracht, die niet noodzakelijk onze klant zijn;
- bezoekers van de website.

## Verwerkte gegevens
Contactpersonen: naam, functie, professioneel adres, telefoon, e-mail, taal, btw-nummer van de onderneming, geschiedenis van zendingen, facturen en betalingen, aanmeldlogboeken.

Chauffeurs en personeel: identiteit, contactgegevens, geboortedatum, statuut (werknemer of zelfstandige), datum van indiensttreding en, in voorkomend geval, datum en reden van uitdiensttreding (pensioen, ontslag door de werknemer, ontslag door de werkgever, medische ongeschiktheid, verval van het recht tot sturen); rijbewijsnummer en categorieën; geldigheidsdata van het rijbewijs, de code 95, de bestuurderskaart en het ADR-certificaat; datum van het medisch onderzoek; totaal aantal rijuren van de dag; periodes van onbeschikbaarheid en hun reden (verlof, ziekte, opleiding); toewijzing aan opdrachten, tijdstippen van ophaling en levering en de hieronder beschreven posities; datum, versie en IP-adres van de kennisname van de informatienota. De redenen „ziekte” en „medische ongeschiktheid” worden enkel als categorie geregistreerd.

Afzenders en geadresseerden: naam, ophaal- of leveradres, contacttelefoon, naam van wie de goederen in ontvangst nam en eventueel voorbehoud.

Bezoekers: het IP-adres en de identificatie van de browser, geregistreerd met de sessie en gewist na het verstrijken ervan (twee uur zonder activiteit); de keuze gemaakt in de cookiebanner; met hun toestemming, de publieksmeting beschreven in het cookiebeleid. Het concept van het offerteformulier wordt in de browser van de bezoeker bewaard, nooit op onze servers, tot de aanvraag verzonden is of tot hij het wist.

## Doeleinden en rechtsgronden
- De vervoersovereenkomst uitvoeren, voertuigen en chauffeurs toewijzen, zendingen opvolgen — uitvoering van de overeenkomst.
- Facturen opmaken, schulden invorderen, boekhouding voeren — wettelijke verplichting en gerechtvaardigd belang.
- De verplichtingen inzake rij- en rusttijden en vakbekwaamheid naleven — wettelijke verplichting.
- Buiten de toepassing de vervoersdocumenten opmaken, waaronder de CMR-vrachtbrief, en de douaneaangiften — wettelijke verplichting.
- De toepassing beveiligen en afwijkende toegang opsporen — gerechtvaardigd belang.
- Offerteaanvragen beantwoorden — precontractuele maatregelen.
- Het bezoek aan de openbare website meten, zonder bezoekers te identificeren — toestemming, op elk ogenblik in te trekken via de link „Cookies beheren”.

## Gelokaliseerde opvolging van zendingen
De opvolging steunt op de volgende elementen.

De coördinaten van het ophaal- en het leveradres, eenmalig gegeocodeerd bij het aanmaken van de opdracht. De statuswijzigingen die de chauffeur invoert, met hun tijdstip. Twee positie-ijkpunten, opgenomen op het ogenblik waarop hij de ophaling en vervolgens de levering aangeeft: dat zijn beheersfeiten, een jaar bewaard zoals een vermelding op een vrachtbrief. Tot slot, wanneer de chauffeur zelf een ongeval, een panne of schade meldt, de positie van die melding: zij wordt in het activiteitenlogboek bewaard en per e-mail naar de administratie en de planning gestuurd.

## Positieopvolging tijdens de rit
Voor bepaalde opdrachten, en enkel voor die, wordt een tussentijdse positie opgenomen terwijl de goederen onderweg zijn. Die mogelijkheid wordt opdracht per opdracht geopend, nooit voor de hele vloot, en de beslissing wordt gelogd met de naam van wie ze nam.

Een voertuig lokaliseren komt neer op het lokaliseren van de chauffeur: dat is een gegeven over een werknemer. De Gegevensbeschermingsautoriteit aanvaardt zo'n verwerking wanneer zij een welbepaald professioneel doel nastreeft, maar acht een permanent en systematisch toezicht onevenredig. Het systeem is daarom begrensd:

- de positie vertrekt enkel vanaf het scherm van de chauffeur, tijdens een lopende opdracht; er bestaat geen kastje dat buiten het werk uitzendt;
- een zichtbare banner meldt hem op datzelfde ogenblik dat zijn positie wordt gedeeld;
- hoogstens één opname om de vijf minuten, een cadans die de server oplegt;
- tijdens de rit ziet de klant slechts één punt, het laatst gekende, op de route van zijn eigen zending; hij krijgt nooit toegang tot het spoor van de rit. Voor zijn zending ziet hij ook de twee ijkpunten van ophaling en levering met hun tijdstip, en de naam van de chauffeur, zijn telefoonnummer, zijn rijbewijscategorie en zijn ADR-bevoegdheid;
- geen enkel scherm van de toepassing laat toe de tijdens de rit opgenomen posities van een bepaalde chauffeur te raadplegen. Het activiteitenlogboek, voorbehouden aan de beheerder, bewaart wel het tijdstip van elke ophaling, levering en elk incident, met de naam van de chauffeur; het dient voor de beveiliging en de afhandeling van geschillen, niet voor het toezicht op de arbeidstijd;
- die posities dienen nooit om een chauffeur te beoordelen, te sanctioneren of te quoteren;
- zij worden automatisch gewist zeven dagen na de levering of de annulering, of dertig dagen na de laatste bijwerking van een opdracht die onderweg bleef, door een geplande taak en niet door een manuele ingreep.

De chauffeurs worden vooraf en individueel over dit systeem geïnformeerd, via een nota die hun wordt toegestuurd en waarvan zij in de toepassing kennisname bevestigen. De datum, de versie van de nota en het IP-adres van die kennisname worden geregistreerd; zij is een voorwaarde voor de opname: zolang zij niet gegeven is, wordt geen enkele positie geregistreerd, ook al zou de opvolging voor de opdracht geopend zijn. Een herschreven nota moet opnieuw worden gelezen.

Die bevestiging is geen toestemming. In een arbeidsrelatie is toestemming niet vrij gegeven en kan zij de verwerking niet gronden; wat hier bewezen wordt is de voorafgaande informatie, niet een akkoord.

## Wat de toepassing niet doet
De tachograaf wordt niet uitgelezen. Het totaal aantal rijuren van de dag dient om te plannen binnen de grenzen van verordening (EG) nr. 561/2006; het dient niet om toezicht te houden. De tijdens de rit opgenomen posities worden niet bewaard buiten de hierboven vermelde termijn; de twee ijkpunten van ophaling en levering worden een jaar na de levering gewist, en de positie van een gemeld incident blijft twaalf maanden in het activiteitenlogboek.

## Ontvangers en verwerkers
Gegevens worden niet verkocht, verhuurd of geruild. Zij worden meegedeeld, beperkt tot het noodzakelijke:
- aan de hostingpartijen van de toepassing: Render (Render Services, Inc.) voor de server en Supabase (Supabase, Inc.) voor de databank, in hun regio Frankfurt (Duitsland);
- aan de e-maildienst Brevo, die de e-mails van de toepassing verstuurt (bevestiging van het adres, wachtwoord, zendingsberichten, facturen, informatienota voor chauffeurs, incidentmeldingen) en het adres van de ontvanger en de inhoud van het bericht ontvangt;
- aan de aanbieder van onlinebetalingen Stripe (Stripe Payments Europe, Ierland), die het e-mailadres van de betaler, het bedrag en de factuurreferentie ontvangt;
- aan de openbare routeberekeningsservers op basis van OpenStreetMap (router.project-osrm.org en, als reserve, routing.openstreetmap.de), die de coördinaten van het ophaal- en leverpunt ontvangen, zonder naam of dossierreferentie;
- aan de openbare Overpass-servers van OpenStreetMap, die een straatnaam en een tot ongeveer een kilometer afgeronde positie ontvangen om de huisnummers voor te stellen, of de omtrek van een route om de tolpunten te situeren, zonder naam of dossierreferentie;
- aan de VIES-dienst van de Europese Commissie en aan de nationale ondernemingsregisters (België, Frankrijk, Zwitserland, Noorwegen, Verenigd Koninkrijk, Tsjechië, Finland, Polen, Roemenië), die enkel het ingevoerde btw-nummer ontvangen, om de identiteit van een onderneming te verifiëren;
- aan de diensten voor adresopzoeking en kaartachtergronden (Photon van komoot, de Franse Base adresse nationale, het Nederlandse PDOK, OpenFreeMap en, als reserve, de tegelservers van OpenStreetMap), rechtstreeks aangesproken door de browser, die de ingevoerde adrestekst of het getoonde kaartgebied en het IP-adres van de browser ontvangen;
- aan de klant, voor de chauffeur die aan zijn zending is toegewezen: naam, telefoonnummer, rijbewijscategorie en ADR-bevoegdheid;
- aan onderaannemers in het vervoer wanneer een zending hen wordt toevertrouwd;
- aan het boekhoudkantoor, de verzekeraar en, in voorkomend geval, de juridisch raadsman;
- aan de overheid wanneer de wet dat oplegt, met name inzake fiscaliteit, douane en sociale zekerheid.

Render, Supabase, Brevo en Stripe treden op als verwerkers, binnen hun voorwaarden voor gegevensverwerking (artikel 28 AVG). De openbare route-, adres- en kaartdiensten worden zonder account gebruikt, onder hun eigen voorwaarden; zij ontvangen geen naam of dossierreferentie.

## Doorgifte buiten de Europese Unie
Gegevens worden binnen de Europese Unie verwerkt. Uitzonderingen:
- de verificatie van een Zwitsers of Brits btw-nummer, doorgegeven aan het register van dat land, dat een adequaatheidsbesluit van de Europese Commissie geniet;
- de onlinebetaling: Stripe behoort tot een groep gevestigd in de Verenigde Staten, aangesloten bij het EU-VS-kader voor gegevensbescherming en gebonden door de modelcontractbepalingen van de Commissie;
- een levering buiten de Unie, die de doorgifte van de gegevens van de geadresseerde vereist, op basis van de waarborgen van hoofdstuk V AVG;
- de hosting: Render en Supabase zijn ondernemingen gevestigd in de Verenigde Staten; de gegevens worden in Frankfurt opgeslagen, maar toegang vanuit de Verenigde Staten blijft mogelijk voor ondersteuning en onderhoud; die is omkaderd door de modelcontractbepalingen van de Commissie in hun voorwaarden voor gegevensverwerking.

## Bewaartermijnen
- Facturen, creditnota's, betalingen en andere boekhoudkundige stukken: zeven jaar vanaf 1 januari van het jaar dat volgt op hun jaar (artikel 60 van het Btw-wetboek, gewijzigd bij de wet van 18 december 2025; artikel III.88 van het Wetboek van economisch recht), daarna automatische wissing.
- Vervoersopdrachten, met de namen en telefoonnummers van contactpersonen, de naam van wie de goederen in ontvangst nam en het voorbehoud: de bewaartermijn van de factuur waarvan zij het verantwoordingsstuk zijn, daarna automatische wissing; een opdracht die zonder kosten werd geannuleerd: drie jaar na de annulering.
- Tijdens de rit opgenomen posities: zeven dagen na de levering of de annulering, of dertig dagen na de laatste bijwerking van een opdracht die onderweg bleef; ijkpunten van ophaling en levering: een jaar na de levering of de annulering.
- Gegevens van chauffeurs: de duur van de arbeidsrelatie. Een jaar na het vertrek, de verjaringstermijn van de vorderingen uit de arbeidsovereenkomst, worden de beheersgegevens (rijbewijs, onderzoeken, kaarten, data, contactgegevens, onbeschikbaarheden, kennisnames van de nota) gewist; de naam blijft verbonden aan de bewaarde vervoersdossiers en wordt met het laatste gewist.
- Activiteitenlogboek van de toepassing en logboek van de API-oproepen, die onder meer datum, gebruiker, actie en IP-adres registreren: twaalf maanden, daarna automatische wissing. Ingetrokken of vervallen API-sleutels en behandelde toegangsaanvragen: twaalf maanden.
- Offerteaanvragen zonder gevolg, bijlagen inbegrepen: twee jaar vanaf hun ontvangst; omgezet in een bestelling volgen zij de bestelling.
- Bezoekersmeting, zonder gegevens die een bezoeker identificeren: dertien maanden, daarna automatische wissing.
- Klantaccount: zolang de account open is. Bij het verwijderen van de account worden naam, e-mailadres, telefoonnummer en wachtwoord gewist; werd de onderneming al bediend of gefactureerd, dan blijft haar fiche bewaard tot haar laatste stuk het einde van zijn bewaartermijn bereikt. Het e-mailadres blijft in het activiteitenlogboek gedurende de bewaartermijn daarvan. Geweigerde inschrijving: zes maanden na de beslissing, daarna automatische wissing.

## Beveiliging
De toegang tot de toepassing is persoonsgebonden en beperkt tot de rol van elkeen. Wachtwoorden worden nooit in leesbare vorm bewaard. Het verkeer met de server is versleuteld. Gevoelige handelingen worden gelogd, en die logboeken worden automatisch gewist na hun bewaartermijn.

De huidige onlineversie, gehost op gratis formules, heeft nog geen door NBLogiTrack geplande back-up, en de opgeladen bestanden (bijlagen van offerteaanvragen, gepubliceerde documenten) worden op de server van de toepassing opgeslagen. Back-up en herstel worden ingericht met de productiehosting, en deze pagina wordt op dat ogenblik bijgewerkt. Wij vermelden dit liever dan een maatregel aan te kondigen die nog niet bestaat.

## Geautomatiseerde besluitvorming
Er wordt geen besluit met rechtsgevolgen genomen op de enkele grondslag van een geautomatiseerde verwerking. De tariefberekening en de voorstellen tot toewijzing van een voertuig of chauffeur zijn hulpmiddelen: een planner beslist.

## Cookies
De website plaatst de cookies die nodig zijn voor de werking en de beveiliging van de sessie. Enkel met uw toestemming meet hij ook het bezoek, met eigen middelen: er wordt geen IP-adres of identificatiemiddel bij de meting geregistreerd en er komt geen externe dienst aan te pas. Die keuze wijzigt u op elk ogenblik via de link „Cookies beheren” onderaan de pagina; het cookiebeleid geeft de details. Er worden geen reclamecookies geplaatst.

## Uw rechten
U beschikt over een recht op inzage, verbetering, wissing, beperking, bezwaar en overdraagbaarheid. Deze rechten worden schriftelijk uitgeoefend op info@nblogitrack.be. Via zijn profiel kan elke gebruiker zijn gegevens ook downloaden in een bestand dat door een andere toepassing kan worden gelezen; een klantgebruiker kan er zijn account verwijderen. Een antwoord volgt binnen de maand, verlengbaar met twee maanden bij een complexe aanvraag.

Sommige rechten kennen grenzen: gegevens op een factuur of een vrachtbrief kunnen niet worden gewist vóór het verstrijken van de wettelijke bewaartermijn.

## Klacht
U kunt klacht indienen bij de Gegevensbeschermingsautoriteit, Drukpersstraat 35, 1000 Brussel — contact@apd-gba.be — onverminderd elk beroep in rechte.

## Wijzigingen
Dit beleid kan worden aangepast. De datum van de laatste bijwerking staat onderaan deze pagina.
TXT;
    }

    private function viePriveeEn(): string
    {
        return <<<'TXT'
## Data controller
NBLogiTrack SRL, Avenue du Port 86C, 1000 Brussels, company number BE 0123.456.749.
Any question regarding personal data: info@nblogitrack.be

## Who this policy concerns
It describes the processing of data relating to four categories of people:
- contact persons at customer and supplier companies;
- drivers and operations staff;
- senders and consignees named in a transport order, who are not necessarily our customers;
- website visitors.

## Data processed
Contact persons: name, job title, business address, phone, email, language, company VAT number, shipment history, invoices and payments, sign-in logs.

Drivers and staff: identity, contact details, date of birth, status (employee or self-employed), start date and, where applicable, date and reason for leaving (retirement, resignation, dismissal, medical unfitness, driving disqualification); licence number and categories; validity dates of the licence, the CPC (code 95), the driver card and the ADR certificate; date of the medical examination; total driving hours for the day; periods of unavailability and their reason (leave, sickness, training); assignment to missions, pickup and delivery times and the positions described below; date, version and IP address of the acknowledgement of the information notice. The reasons "sickness" and "medical unfitness" are recorded as a mere category.

Senders and consignees: name, pickup or delivery address, contact phone, name of the person who received the goods and any reservations.

Visitors: the IP address and browser identification, recorded with the session and erased once it expires (two hours without activity); the choice made in the cookie banner; with their consent, the audience measurement described in the cookie policy. The draft of the quotation request form is kept in the visitor's browser, never on our servers, until the request is sent or until the visitor deletes it.

## Purposes and legal bases
- Performing the transport contract, assigning vehicles and drivers, tracking shipments — performance of the contract.
- Issuing invoices, recovering debts, keeping accounts — legal obligation and legitimate interest.
- Complying with driving time, rest period and driver qualification rules — legal obligation.
- Producing, outside the application, transport documents, including the CMR consignment note, and customs declarations — legal obligation.
- Securing the application and detecting abnormal access — legitimate interest.
- Answering quotation requests — pre-contractual measures.
- Measuring visits to the public website without identifying visitors — consent, which can be withdrawn at any time through the “Manage cookies” link.

## Geolocated shipment tracking
Tracking rests on the following elements.

The coordinates of the pickup and delivery addresses, geocoded once when the order is created. The status changes entered by the driver, with their time. Two position waypoints, recorded at the moment the driver declares pickup and then delivery: these are business facts, kept for one year like an entry on a consignment note. Finally, when the driver personally reports an accident, a breakdown or damage, the position of that report: it is kept in the activity log and sent by email to the administration and planning staff.

## Position tracking during the journey
For certain missions, and only for those, an intermediate position is recorded while the goods are on the road. This is opened mission by mission, never for the whole fleet, and the decision is logged with the name of whoever took it.

Locating a vehicle amounts to locating its driver: that is data about a worker. The Belgian Data Protection Authority accepts such processing where it pursues a defined professional purpose, but considers permanent, systematic monitoring disproportionate. The arrangement is therefore bounded:

- the position leaves only from the driver's screen, during a mission in progress; no unit transmits outside working time;
- a visible banner tells the driver, at that very moment, that their position is being shared;
- one reading every five minutes at most, a cadence enforced by the server;
- during the journey, the customer sees one point only, the latest known, on the route of their own shipment; they never reach the trace of the journey. For their shipment they also see the two pickup and delivery waypoints with their time, and the driver's name, phone number, licence category and ADR qualification;
- no screen in the application allows the positions recorded during a given driver's journeys to be consulted. The activity log, reserved for the administrator, does however keep the time of each pickup, delivery and incident, with the driver's name; it serves security and dispute handling, not the monitoring of working time;
- these positions are never used to assess, sanction or rate a driver;
- they are erased automatically seven days after delivery or cancellation, or thirty days after the last update of a mission left in progress, by a scheduled task and not by a manual step.

Drivers are informed of this arrangement beforehand and individually, through a notice sent to them and acknowledged in the application. The date, the version of the notice and the IP address of that acknowledgement are recorded; it is a condition of recording: until it is given, no position is stored, even where tracking has been opened for the mission. A rewritten notice must be read again.

This acknowledgement is not consent. In an employment relationship consent is not freely given and cannot found the processing; what is proven here is prior information, not agreement.

## What the application does not do
The tachograph is not read. The daily driving hours total serves to plan within the limits of Regulation (EC) No 561/2006; it does not serve to monitor. Positions recorded during the journey are not kept beyond the period stated above; the two pickup and delivery waypoints are erased one year after delivery, and the position of a reported incident stays in the activity log for twelve months.

## Recipients and processors
Data is never sold, rented or exchanged. It is disclosed, limited to what is necessary:
- to the application's hosting providers: Render (Render Services, Inc.) for the server and Supabase (Supabase, Inc.) for the database, in their Frankfurt region (Germany);
- to the email service Brevo, which delivers the application's emails (address confirmation, password, shipment notices, invoices, information notice for drivers, incident alerts) and receives the recipient's address and the content of the message;
- to the online payment provider Stripe (Stripe Payments Europe, Ireland), which receives the payer's email address, the amount and the invoice reference;
- to the public OpenStreetMap-based routing servers (router.project-osrm.org and, as a fallback, routing.openstreetmap.de), which receive the coordinates of the pickup and delivery points, without any name or file reference;
- to the public OpenStreetMap Overpass servers, which receive a street name and a position rounded to about one kilometre, to suggest house numbers, or the outline of a route, to locate tolls, without any name or file reference;
- to the European Commission's VIES service and to national company registers (Belgium, France, Switzerland, Norway, United Kingdom, Czechia, Finland, Poland, Romania), which receive only the VAT number entered, to verify a company's identity;
- to address lookup and map background services (komoot's Photon, the French Base adresse nationale, the Dutch PDOK, OpenFreeMap and, as a fallback, the OpenStreetMap tile servers), called directly by the browser, which receive the address text entered or the map area displayed, and the browser's IP address;
- to the customer, for the driver assigned to their shipment: name, phone number, licence category and ADR qualification;
- to transport subcontractors when a consignment is entrusted to them;
- to the accounting firm, the insurer and, where applicable, legal counsel;
- to public authorities where the law so requires, in particular in tax, customs and social security matters.

Render, Supabase, Brevo and Stripe act as processors, under their data processing terms (Article 28 GDPR). The public routing, address and map services are used without an account, under their own terms; they receive no name or file reference.

## Transfers outside the European Union
Data is processed within the European Union, with these exceptions:
- checking a Swiss or British VAT number, sent to that country's register, which benefits from an adequacy decision of the European Commission;
- online payment: Stripe belongs to a group established in the United States, certified under the EU–US Data Privacy Framework and bound by the Commission's standard contractual clauses;
- a delivery outside the Union, which requires sending the consignee's data, on the basis of the safeguards provided for in Chapter V GDPR;
- hosting: Render and Supabase are companies established in the United States; data is stored in Frankfurt, but access from the United States remains possible for support and maintenance; it is covered by the Commission's standard contractual clauses included in their data processing terms.

## Retention periods
- Invoices, credit notes, payments and other accounting records: seven years from 1 January following their year (Article 60 of the Belgian VAT Code, as amended by the Law of 18 December 2025; Article III.88 of the Code of Economic Law), then automatic erasure.
- Transport orders, including contact names and phone numbers, the name of the person who received the goods and reservations: the retention period of the invoice they support, then automatic erasure; an order cancelled free of charge: three years after cancellation.
- Positions recorded during the journey: seven days after delivery or cancellation, or thirty days after the last update of a mission left in progress; pickup and delivery waypoints: one year after delivery or cancellation.
- Driver data: the duration of the employment relationship. One year after departure, the limitation period for claims arising from the employment contract, management data (licence, examinations, cards, dates, contact details, unavailability periods, acknowledgements of the notice) are erased; the name stays attached to the transport files still kept and is erased with the last one.
- Application activity log and API call log, which record in particular the date, user, action and IP address: twelve months, then automatic erasure. Revoked or expired API keys and processed access requests: twelve months.
- Quotation requests left without follow-up, attachments included: two years from receipt; once converted into an order, they follow the order.
- Audience measurement, without data identifying a visitor: thirteen months, then automatic erasure.
- Customer account: for as long as it is open. When the account is deleted, the name, email address, phone number and password are erased; if the company has already been served or invoiced, its record is kept until its last record reaches the end of its retention period. The email address remains in the activity log for that log's retention period. Rejected registration: six months after the decision, then automatic erasure.

## Security
Access to the application is personal and limited by each person's role. Passwords are never stored in readable form. Traffic with the server is encrypted. Sensitive actions are logged, and those logs are erased automatically once their retention period expires.

The current online version, hosted on free plans, has no backup scheduled by NBLogiTrack yet, and uploaded files (quotation request attachments, published documents) are stored on the application server. Backup and restoration will be set up with the production hosting, and this page will be updated at that point. We prefer to say so rather than announce a measure that is not yet in place.

## Automated decision-making
No decision producing legal effects is taken on the sole basis of automated processing. Rate calculation and vehicle or driver assignment suggestions are decision aids: a planner validates them.

## Cookies
The website places the cookies required for its operation and session security. Only with your consent does it also measure visits, by its own means: no IP address or identifier is recorded with the measurement and no third-party service is involved. You can change this choice at any time through the “Manage cookies” link at the bottom of the page; the cookie policy gives the details. No advertising cookies are placed.

## Your rights
You have the right of access, rectification, erasure, restriction, objection and portability. These rights are exercised in writing at info@nblogitrack.be. From their profile, every user can also download their data in a file readable by another application; a customer user can delete their account there. A reply is given within one month, extendable by two months where the request is complex.

Some rights have limits: data appearing on an invoice or a consignment note cannot be erased before the statutory retention period expires.

## Complaint
You may lodge a complaint with the Belgian Data Protection Authority, Rue de la Presse 35, 1000 Brussels — contact@apd-gba.be — without prejudice to any judicial remedy.

## Changes
This policy may be amended. The date of the last update appears at the bottom of this page.
TXT;
    }

    private function cookiesFr(): string
    {
        return <<<'TXT'
## Ce qu'est un témoin de connexion
Un témoin de connexion, ou cookie, est un petit fichier que le navigateur conserve pendant la visite d'un site. Il permet au serveur de reconnaître le navigateur d'une page à l'autre, par exemple pour maintenir une session ouverte après la connexion.

## La règle que suit ce site
La loi n'exige un consentement que pour ce qui n'est pas indispensable au service demandé, comme la publicité ou la mesure d'audience. Les témoins énumérés ci-dessous sont strictement nécessaires au fonctionnement et à la sécurité ; ils sont dispensés de consentement. La mesure d'audience, elle, n'a lieu qu'avec votre accord : le bandeau affiché lors de la première visite permet de l'accepter, de la refuser (« Continuer sans accepter ») ou de choisir dans « Personnaliser ». Elle est désactivée tant que vous ne l'avez pas acceptée.

## Les témoins déposés
- nblogitrack-session — maintient la session d'une page à l'autre ; expire après cent vingt minutes d'inactivité.
- XSRF-TOKEN — protège les formulaires contre les requêtes forgées depuis un autre site ; même durée que la session.
- remember_web_ suivi d'une empreinte technique — conserve la connexion lorsque la case « Se souvenir de moi » est cochée ; supprimé à la déconnexion et, à défaut, au terme de trente jours.
- temoins_choix — enregistre le choix exprimé dans le bandeau (« essentiels » ou « audience ») ; conservé cent quatre-vingts jours.

## Le stockage local du navigateur
Le formulaire de demande de devis garde un brouillon de la saisie dans le stockage local du navigateur (nblogitrack.devis.brouillon), pour qu'une page rechargée ne fasse pas tout perdre. Ce brouillon, qui peut contenir vos coordonnées, ne quitte pas votre appareil. Il est effacé à l'envoi de la demande ou par le bouton « Tout effacer » du formulaire ; sinon, il reste dans le navigateur jusqu'à ce que vous effaciez les données du site.

## La mesure d'audience, avec votre accord
Si vous l'acceptez, NBLogiTrack compte lui-même les pages vues de son site public, sans outil ni service tiers. Pour chaque page, il garde la date et l'heure, la page, la langue, le type d'appareil (ordinateur ou mobile) et, à l'arrivée sur le site, la provenance (moteur de recherche, réseau social, site d'origine ou campagne). Il compte aussi les demandes de devis, les inscriptions et les simulations de tarif. Aucun témoin de suivi n'est déposé, aucune adresse IP n'est enregistrée avec ces mesures et rien ne relie deux pages au même visiteur ; pour écarter les envois automatisés, une empreinte salée de l'adresse IP sert pendant une minute de compteur temporaire, puis expire. Les comptes connectés ne sont pas mesurés. Ces chiffres servent à améliorer le site ; ils sont conservés treize mois puis effacés.

## Ce que ce site ne dépose pas
Aucun témoin publicitaire. Aucun traceur de mesure d'audience d'un tiers. Aucun témoin de réseau social. Aucune donnée n'est transmise à un tiers par ce moyen.

## Le paiement en ligne
Le règlement d'une facture s'effectue sur la page de paiement hébergée par Stripe, sur le domaine de Stripe. Les témoins que Stripe y dépose relèvent de sa propre politique, consultable sur son site. Aucun témoin de Stripe n'est déposé sur le présent site.

## Revenir sur son choix
Le lien « Gérer les cookies », au pied des pages du site, rouvre le bandeau à tout moment. Revenir sur son choix est ainsi aussi simple que de l'exprimer.

## Gérer les témoins dans le navigateur
Chaque navigateur permet de consulter, de bloquer et de supprimer les témoins depuis ses paramètres de confidentialité. Bloquer les témoins essentiels empêche toutefois la connexion à l'espace client : sans témoin de session, le serveur ne peut pas reconnaître l'utilisateur.

## Questions
Toute question relative aux témoins peut être adressée à info@nblogitrack.be. La politique de confidentialité, accessible au pied de page, décrit l'ensemble des traitements de données.
TXT;
    }

    private function cookiesNl(): string
    {
        return <<<'TXT'
## Wat een cookie is
Een cookie is een klein bestand dat de browser bewaart tijdens het bezoek aan een website. Het laat de server toe de browser van pagina tot pagina te herkennen, bijvoorbeeld om een sessie open te houden na het aanmelden.

## De regel die deze website volgt
De wet vereist enkel toestemming voor wat niet onmisbaar is voor de gevraagde dienst, zoals reclame of publieksmeting. De hieronder opgesomde cookies zijn strikt noodzakelijk voor de werking en de beveiliging; zij zijn vrijgesteld van toestemming. De publieksmeting gebeurt enkel met uw toestemming: de banner bij het eerste bezoek laat toe ze te aanvaarden, te weigeren (« Doorgaan zonder te aanvaarden ») of te kiezen via « Aanpassen ». Zolang u ze niet aanvaardt, staat ze uit.

## De geplaatste cookies
- nblogitrack-session — houdt de sessie in stand van pagina tot pagina; vervalt na honderdtwintig minuten inactiviteit.
- XSRF-TOKEN — beschermt de formulieren tegen vervalste verzoeken vanaf een andere website; zelfde duur als de sessie.
- remember_web_ gevolgd door een technische vingerafdruk — houdt de aanmelding aan wanneer het vakje « Onthoud mij » is aangevinkt; gewist bij het afmelden en anders na dertig dagen.
- temoins_choix — registreert de keuze die in de banner werd gemaakt (« essentiels » of « audience »); honderdtachtig dagen bewaard.

## De lokale opslag van de browser
Het offerteformulier bewaart een concept van de invoer in de lokale opslag van de browser (nblogitrack.devis.brouillon), zodat een herladen pagina niet alles doet verliezen. Dat concept, dat uw contactgegevens kan bevatten, verlaat uw toestel niet. Het wordt gewist bij het verzenden van de aanvraag of met de knop « Alles wissen » van het formulier; anders blijft het in de browser tot u de gegevens van de website wist.

## Publieksmeting, met uw toestemming
Als u ermee instemt, telt NBLogiTrack zelf de bekeken pagina's van zijn publieke website, zonder tool of dienst van derden. Per pagina bewaart het de datum en het uur, de pagina, de taal, het type toestel (computer of mobiel) en, bij aankomst op de website, de herkomst (zoekmachine, sociaal netwerk, verwijzende website of campagne). Het telt ook de offerteaanvragen, de inschrijvingen en de tariefsimulaties. Er wordt geen trackingcookie geplaatst, geen IP-adres bij deze metingen geregistreerd en niets verbindt twee pagina's met dezelfde bezoeker; om geautomatiseerde verzoeken te weren dient een gezouten vingerafdruk van het IP-adres gedurende één minuut als tijdelijke teller, waarna hij vervalt. Aangemelde accounts worden niet gemeten. Deze cijfers dienen om de website te verbeteren; ze worden dertien maanden bewaard en daarna gewist.

## Wat deze website niet plaatst
Geen reclamecookies. Geen trackers voor publieksmeting van derden. Geen cookies van sociale netwerken. Langs deze weg wordt geen enkel gegeven aan een derde doorgegeven.

## De onlinebetaling
De betaling van een factuur gebeurt op de betaalpagina die Stripe host, op het domein van Stripe. De cookies die Stripe daar plaatst, vallen onder zijn eigen beleid, raadpleegbaar op zijn website. Op deze website wordt geen enkele cookie van Stripe geplaatst.

## Op een keuze terugkomen
De link « Cookies beheren », onderaan de pagina's van de website, opent de banner op elk ogenblik opnieuw. Op een keuze terugkomen is zo even eenvoudig als ze uiten.

## Cookies beheren in de browser
Elke browser laat toe cookies te raadplegen, te blokkeren en te wissen via de privacyinstellingen. Het blokkeren van de essentiële cookies verhindert evenwel de aanmelding in de klantenzone: zonder sessiecookie kan de server de gebruiker niet herkennen.

## Vragen
Elke vraag over cookies kan worden gericht aan info@nblogitrack.be. Het privacybeleid, bereikbaar onderaan de pagina, beschrijft alle gegevensverwerkingen.
TXT;
    }

    private function cookiesEn(): string
    {
        return <<<'TXT'
## What a cookie is
A cookie is a small file the browser keeps while visiting a website. It lets the server recognise the browser from one page to the next, for instance to keep a session open after signing in.

## The rule this website follows
The law requires consent only for what is not essential to the requested service, such as advertising or audience measurement. The cookies listed below are strictly necessary for operation and security; they are exempt from consent. Audience measurement only takes place with your consent: the banner shown on the first visit lets you accept it, refuse it (« Continue without accepting ») or choose under « Customise ». It stays off until you accept it.

## Cookies placed
- nblogitrack-session — keeps the session alive from page to page; expires after one hundred and twenty minutes of inactivity.
- XSRF-TOKEN — protects forms against requests forged from another website; same lifetime as the session.
- remember_web_ followed by a technical fingerprint — keeps you signed in when the « Remember me » box is ticked; deleted on sign-out and otherwise after thirty days.
- temoins_choix — records the choice made in the banner (« essentiels » or « audience »); kept for one hundred and eighty days.

## The browser's local storage
The quotation request form keeps a draft of what you type in the browser's local storage (nblogitrack.devis.brouillon), so that a reloaded page does not lose everything. This draft, which may contain your contact details, does not leave your device. It is deleted when the request is sent or with the form's « Clear everything » button; otherwise it stays in the browser until you clear the website's data.

## Audience measurement, with your consent
If you accept it, NBLogiTrack itself counts the pages viewed on its public website, without any third-party tool or service. For each page it keeps the date and time, the page, the language, the device type (computer or mobile) and, on arrival on the website, where the visit came from (search engine, social network, referring website or campaign). It also counts quote requests, sign-ups and rate simulations. No tracking cookie is placed, no IP address is recorded with these measurements and nothing links two pages to the same visitor; to keep out automated requests, a salted fingerprint of the IP address serves for one minute as a temporary counter, then expires. Signed-in accounts are not measured. These figures are used to improve the website; they are kept for thirteen months and then deleted.

## What this website does not place
No advertising cookies. No third-party audience measurement trackers. No social network cookies. No data is passed to any third party by this means.

## Online payment
Invoices are paid on the payment page hosted by Stripe, on Stripe's domain. The cookies Stripe places there fall under its own policy, available on its website. No Stripe cookie is placed on this website.

## Changing your mind
The « Manage cookies » link, in the footer of the website's pages, reopens the banner at any time. Going back on a choice is as simple as making it.

## Managing cookies in the browser
Every browser allows cookies to be viewed, blocked and deleted from its privacy settings. Blocking the essential cookies however prevents signing in to the customer area: without a session cookie, the server cannot recognise the user.

## Questions
Any question about cookies may be sent to info@nblogitrack.be. The privacy policy, available in the page footer, describes all data processing.
TXT;
    }

    private function conditionsFr(): string
    {
        return <<<'TXT'
## Article 1 — Définitions
Transporteur : NBLogiTrack SRL. Donneur d'ordre : la personne qui confie l'exécution d'un transport, qu'elle soit ou non propriétaire de la marchandise. Envoi : l'ensemble des marchandises confiées en une fois pour un même trajet. CMR : la Convention relative au contrat de transport international de marchandises par route, signée à Genève le 19 mai 1956.

## Article 2 — Champ d'application
Les présentes conditions s'appliquent à toute offre, tout ordre et tout transport exécuté par le transporteur. Elles s'adressent exclusivement à des entreprises et ne régissent aucune relation de consommation.

L'acceptation d'un ordre emporte acceptation des présentes conditions. Les conditions d'achat du donneur d'ordre ne s'appliquent pas, même communiquées ultérieurement et non contestées, sauf acceptation écrite et expresse du transporteur.

## Article 3 — Cadre normatif
Les transports internationaux sont régis par la CMR. Les transports nationaux sont régis par le droit belge, notamment la loi du 15 juillet 2013 relative au transport de marchandises par route. Les présentes conditions complètent ce cadre, dans l'esprit des conditions générales de transport routier établies conjointement par les fédérations professionnelles belges.

En cas de contradiction, la disposition impérative de la CMR prévaut.

## Article 4 — Offres et formation du contrat
Les prix issus du simulateur en ligne sont indicatifs et n'engagent pas le transporteur. Une offre écrite est valable quinze jours, sauf mention contraire.

Le contrat est formé lorsque le transporteur confirme l'ordre. Une réservation enregistrée dans l'application vaut ordre dès sa confirmation.

## Article 5 — Obligations du donneur d'ordre
Le donneur d'ordre garantit que :
- la marchandise est emballée de manière à supporter le transport et les manutentions normales ;
- les colis sont étiquetés et identifiables ;
- le poids et le volume annoncés sont exacts ;
- les documents nécessaires au transport, à la douane et aux formalités administratives sont remis à temps et sont complets ;
- l'accès aux lieux d'enlèvement et de livraison est praticable pour le type de véhicule commandé.

Le donneur d'ordre répond des conséquences d'une déclaration inexacte, notamment d'une surcharge constatée en contrôle routier.

## Article 6 — Chargement, déchargement et temps d'attente
Sauf convention écrite contraire, le chargement et le déchargement incombent respectivement à l'expéditeur et au destinataire. L'arrimage est effectué sous la responsabilité du transporteur ; le calage à l'intérieur des colis relève de l'expéditeur.

Une durée d'immobilisation de deux heures est comprise dans le prix, à l'enlèvement comme à la livraison. Au-delà, le temps d'attente est facturé au tarif horaire en vigueur, par tranche entamée de trente minutes.

Si l'enlèvement ou la livraison ne peut avoir lieu pour une cause étrangère au transporteur, les frais de retour, d'entreposage et de nouvelle présentation sont à charge du donneur d'ordre.

## Article 7 — Matières dangereuses
Aucune marchandise soumise à l'ADR n'est acceptée sans déclaration écrite préalable et complète, mentionnant le numéro ONU, la classe, le groupe d'emballage et les quantités.

Une marchandise dangereuse remise sans cette déclaration peut être déchargée, détruite ou rendue inoffensive sans indemnité, conformément à l'article 22 de la CMR. Le donneur d'ordre supporte les frais et les conséquences.

## Article 8 — Prix
Les prix sont établis hors taxes, sur la base des éléments communiqués lors de la commande. Ils comprennent le transport et l'assurance de responsabilité du transporteur.

Sont facturés en supplément : les temps d'attente au-delà de la franchise, les prestations non prévues, les frais de péage exceptionnels, les retours à vide et toute sujétion résultant d'une information inexacte.

Une variation significative et durable du prix du carburant peut donner lieu à un ajustement, notifié par écrit et applicable aux transports postérieurs à la notification.

## Article 8 bis — Annulation par le donneur d'ordre
Le donneur d'ordre peut annuler une expédition depuis son espace client tant que la marchandise n'a pas été chargée.

L'annulation est gratuite tant qu'aucun véhicule n'a été affecté à l'expédition. Dès qu'un véhicule et un chauffeur lui sont réservés, l'annulation entraîne une indemnité forfaitaire de vingt-cinq pour cent du prix convenu hors taxes, avec un minimum de cinquante euros, sans pouvoir dépasser ce prix. Cette indemnité couvre l'immobilisation du véhicule et du chauffeur ; elle figure sur la facture du mois de l'annulation.

Une fois la marchandise chargée, l'expédition ne peut plus être annulée en ligne. Un retour ou un déroutement se traite alors par écrit et donne lieu à facturation des prestations effectuées.

## Article 8 ter — Droit de rétractation
Les services du transporteur sont réservés aux entreprises. Le droit de rétractation prévu par les articles VI.47 et suivants du Code de droit économique protège les consommateurs ; il ne s'applique pas aux commandes passées par une entreprise pour les besoins de son activité.

Le donneur d'ordre conserve la faculté d'annuler une expédition dans les conditions de l'article 8 bis.

## Article 9 — Facturation et paiement
Les factures sont émises par voie électronique et payables à trente jours de date de facture, sans escompte, sauf convention écrite contraire.

À défaut de paiement à l'échéance, et sans mise en demeure préalable, sont dus de plein droit :
- un intérêt de retard au taux prévu par la loi du 2 août 2002 concernant la lutte contre le retard de paiement dans les transactions commerciales ;
- une indemnité forfaitaire de quarante euros pour frais de recouvrement, conformément à la même loi ;
- au-delà de ce forfait, le remboursement des autres frais de recouvrement raisonnablement exposés, sur justificatif, conformément à l'article 6 de la même loi.

Le non-paiement d'une facture à son échéance rend immédiatement exigibles toutes les autres factures, même non échues, et autorise le transporteur à suspendre les prestations en cours après notification écrite.

Le transporteur peut fixer à chaque donneur d'ordre un délai de paiement et un plafond de crédit, auquel est comparé le total, toutes taxes comprises, des factures émises et non réglées. Une nouvelle expédition ne peut pas être commandée si ce total, augmenté de son prix toutes taxes comprises, dépasserait le plafond, ni tant que trois factures restent impayées après leur échéance ; les expéditions déjà confiées ne sont pas remises en cause par ce seul fait.

## Article 10 — Contestation d'une facture
Toute contestation doit parvenir par écrit dans les dix jours calendrier de la date de facture, avec l'indication précise des motifs. Passé ce délai, la facture est présumée acceptée, sauf preuve contraire.

Une contestation portant sur une partie de la facture ne dispense pas du paiement du solde non contesté.

## Article 11 — Responsabilité du transporteur
La responsabilité du transporteur pour perte ou avarie est limitée, conformément à l'article 23 de la CMR, à 8,33 unités de compte, soit droits de tirage spéciaux, par kilogramme de poids brut manquant.

En cas de retard, l'indemnité ne peut excéder le prix du transport, conformément à l'article 23, paragraphe 5, de la CMR.

Le transporteur ne répond pas des dommages indirects, notamment de la perte de production, du manque à gagner, de la perte de clientèle ou des pénalités contractuelles convenues entre le donneur d'ordre et ses propres clients.

## Article 12 — Déclaration de valeur et intérêt spécial
Le donneur d'ordre qui souhaite dépasser les limites de l'article précédent peut, contre supplément de prix convenu, déclarer la valeur de la marchandise au sens de l'article 24 de la CMR ou un intérêt spécial à la livraison au sens de l'article 26. La déclaration doit être faite avant l'enlèvement et portée sur la lettre de voiture.

## Article 13 — Réserves et réclamations
Les pertes ou avaries apparentes doivent faire l'objet de réserves écrites, précises et motivées, portées sur la lettre de voiture au moment de la réception.

Les dommages non apparents doivent être signalés par écrit dans les sept jours de la livraison, dimanches et jours fériés non compris, conformément à l'article 30 de la CMR.

Un retard ne donne lieu à indemnité que si une réserve écrite est adressée dans les vingt et un jours de la mise à disposition de la marchandise.

## Article 14 — Prescription
Les actions découlant du contrat de transport se prescrivent par un an, conformément à l'article 32 de la CMR. Le délai est de trois ans en cas de dol ou de faute équivalente au dol.

## Article 15 — Empêchement et force majeure
Le transporteur n'est pas responsable de l'inexécution due à un événement échappant à son contrôle, notamment un blocage routier, une intempérie exceptionnelle, une grève, une décision d'autorité ou une fermeture d'infrastructure.

En cas d'empêchement au transport ou à la livraison, le transporteur demande des instructions et, à défaut de réponse utile, prend les mesures qui paraissent les meilleures dans l'intérêt de l'ayant droit, conformément aux articles 14 à 16 de la CMR.

## Article 16 — Sous-traitance
Le transporteur peut confier tout ou partie de l'exécution à un sous-traitant régulièrement licencié et assuré, sans que cela ne modifie ses obligations envers le donneur d'ordre.

## Article 17 — Droit de rétention et gage
Toutes les marchandises, documents et sommes détenus par le transporteur pour le compte du donneur d'ordre constituent le gage du paiement de toute somme due, y compris au titre d'expéditions antérieures. Le transporteur dispose d'un droit de rétention sur ces biens jusqu'à complet paiement.

## Article 18 — Données personnelles
Le traitement des données personnelles est décrit dans la politique de confidentialité, accessible depuis le pied de page du site. Le donneur d'ordre garantit avoir informé les personnes dont il communique les données, notamment les contacts d'enlèvement et de livraison.

## Article 19 — Nullité partielle et renonciation
La nullité d'une clause n'affecte pas la validité des autres. La clause nulle est remplacée par une disposition valable de portée économique équivalente.

Le fait de ne pas exercer un droit à un moment donné ne vaut pas renonciation à l'exercer ultérieurement.

## Article 20 — Droit applicable et juridiction
Les présentes conditions sont régies par le droit belge. Sans préjudice de l'article 31 de la CMR, tout litige relève de la compétence exclusive des tribunaux de l'arrondissement judiciaire de Bruxelles.
TXT;
    }

    private function conditionsNl(): string
    {
        return <<<'TXT'
## Artikel 1 — Definities
Vervoerder: NBLogiTrack BV. Opdrachtgever: wie de uitvoering van een vervoer toevertrouwt, al dan niet eigenaar van de goederen. Zending: het geheel van goederen dat in één keer voor eenzelfde traject wordt toevertrouwd. CMR: het Verdrag betreffende de overeenkomst tot internationaal vervoer van goederen over de weg, ondertekend te Genève op 19 mei 1956.

## Artikel 2 — Toepassingsgebied
Deze voorwaarden gelden voor elk aanbod, elke opdracht en elk vervoer uitgevoerd door de vervoerder. Zij richten zich uitsluitend tot ondernemingen en regelen geen consumentenrelatie.

De aanvaarding van een opdracht houdt de aanvaarding van deze voorwaarden in. De aankoopvoorwaarden van de opdrachtgever zijn niet van toepassing, ook niet wanneer zij later worden meegedeeld en onbetwist blijven, behoudens uitdrukkelijke schriftelijke aanvaarding door de vervoerder.

## Artikel 3 — Normatief kader
Internationaal vervoer wordt beheerst door de CMR. Nationaal vervoer wordt beheerst door het Belgisch recht, met name de wet van 15 juli 2013 betreffende het goederenvervoer over de weg. Deze voorwaarden vullen dat kader aan, in de geest van de algemene vervoersvoorwaarden die de Belgische beroepsfederaties gezamenlijk hebben opgesteld.

Bij tegenstrijdigheid heeft de dwingende bepaling van de CMR voorrang.

## Artikel 4 — Aanbod en totstandkoming
De prijzen van de onlinesimulator zijn indicatief en verbinden de vervoerder niet. Een schriftelijk aanbod is vijftien dagen geldig, behoudens andersluidende vermelding.

De overeenkomst komt tot stand wanneer de vervoerder de opdracht bevestigt. Een in de toepassing geregistreerde boeking geldt als opdracht zodra ze is bevestigd.

## Artikel 5 — Verplichtingen van de opdrachtgever
De opdrachtgever waarborgt dat:
- de goederen zo verpakt zijn dat zij het vervoer en de normale behandeling doorstaan;
- de colli geëtiketteerd en identificeerbaar zijn;
- het opgegeven gewicht en volume juist zijn;
- de documenten voor vervoer, douane en administratieve formaliteiten tijdig en volledig worden overhandigd;
- de laad- en losplaatsen toegankelijk zijn voor het bestelde voertuigtype.

De opdrachtgever staat in voor de gevolgen van een onjuiste opgave, met name van een bij wegcontrole vastgestelde overlading.

## Artikel 6 — Laden, lossen en wachttijden
Behoudens andersluidende schriftelijke afspraak rusten het laden en het lossen respectievelijk op de afzender en de geadresseerde. De lading wordt gezekerd onder verantwoordelijkheid van de vervoerder; de stuwing binnen de colli komt de afzender toe.

Een stilstand van twee uur is in de prijs begrepen, zowel bij het laden als bij het lossen. Daarna wordt de wachttijd aangerekend tegen het geldende uurtarief, per begonnen halfuur.

Kan het laden of lossen niet doorgaan om een reden vreemd aan de vervoerder, dan zijn de kosten van terugkeer, opslag en nieuwe aanbieding ten laste van de opdrachtgever.

## Artikel 7 — Gevaarlijke stoffen
Aan het ADR onderworpen goederen worden niet aanvaard zonder voorafgaande en volledige schriftelijke aangifte met vermelding van het UN-nummer, de klasse, de verpakkingsgroep en de hoeveelheden.

Gevaarlijke goederen die zonder die aangifte worden aangeboden, mogen zonder vergoeding worden gelost, vernietigd of onschadelijk gemaakt, overeenkomstig artikel 22 CMR. De opdrachtgever draagt de kosten en de gevolgen.

## Artikel 8 — Prijzen
Prijzen zijn exclusief belastingen en worden bepaald op basis van de bij de bestelling meegedeelde gegevens. Zij omvatten het vervoer en de aansprakelijkheidsverzekering van de vervoerder.

Worden bijkomend aangerekend: wachttijden boven de vrijstelling, niet voorziene prestaties, uitzonderlijke tolkosten, lege terugritten en elke bijkomende last die voortvloeit uit onjuiste informatie.

Een aanzienlijke en duurzame wijziging van de brandstofprijs kan aanleiding geven tot een aanpassing, schriftelijk meegedeeld en van toepassing op transporten na de kennisgeving.

## Artikel 8 bis — Annulering door de opdrachtgever
De opdrachtgever kan een zending annuleren vanuit zijn klantenzone zolang de goederen niet geladen zijn.

De annulering is kosteloos zolang geen voertuig aan de zending is toegewezen. Zodra een voertuig en een chauffeur ervoor gereserveerd zijn, brengt de annulering een forfaitaire vergoeding mee van vijfentwintig procent van de overeengekomen prijs exclusief btw, met een minimum van vijftig euro, zonder die prijs te mogen overschrijden. Deze vergoeding dekt de immobilisatie van voertuig en chauffeur; ze wordt opgenomen in de factuur van de maand van de annulering.

Zodra de goederen geladen zijn, kan de zending niet meer online geannuleerd worden. Een terugzending of omleiding wordt dan schriftelijk geregeld en de uitgevoerde prestaties worden gefactureerd.

## Artikel 8 ter — Herroepingsrecht
De diensten van de vervoerder zijn voorbehouden aan ondernemingen. Het herroepingsrecht van de artikelen VI.47 en volgende van het Wetboek van economisch recht beschermt consumenten; het geldt niet voor bestellingen die een onderneming plaatst voor haar beroepsactiviteit.

De opdrachtgever behoudt de mogelijkheid om een zending te annuleren onder de voorwaarden van artikel 8 bis.

## Artikel 9 — Facturatie en betaling
Facturen worden elektronisch uitgereikt en zijn betaalbaar binnen dertig dagen na factuurdatum, zonder korting, behoudens andersluidende schriftelijke afspraak.

Bij niet-betaling op de vervaldag zijn van rechtswege en zonder ingebrekestelling verschuldigd:
- een verwijlintrest tegen de rentevoet van de wet van 2 augustus 2002 betreffende de bestrijding van de betalingsachterstand bij handelstransacties;
- een forfaitaire vergoeding van veertig euro voor invorderingskosten, conform diezelfde wet;
- boven dat forfait, de terugbetaling van de andere redelijkerwijs gemaakte invorderingskosten, op bewijs, conform artikel 6 van diezelfde wet.

De niet-betaling van één factuur op haar vervaldag maakt alle andere facturen onmiddellijk opeisbaar, ook de niet-vervallen, en machtigt de vervoerder om lopende prestaties op te schorten na schriftelijke kennisgeving.

De vervoerder kan voor elke opdrachtgever een betalingstermijn en een kredietlimiet vastleggen, waaraan het totaal, btw inbegrepen, van de uitgereikte en onbetaalde facturen getoetst wordt. Een nieuwe zending kan niet besteld worden als dat totaal, verhoogd met haar prijs inclusief btw, de limiet zou overschrijden, noch zolang drie facturen na hun vervaldag onbetaald blijven; de reeds toevertrouwde zendingen komen daardoor alleen niet in het gedrang.

## Artikel 10 — Betwisting van een factuur
Elke betwisting moet schriftelijk toekomen binnen tien kalenderdagen na factuurdatum, met nauwkeurige opgave van de redenen. Na die termijn wordt de factuur vermoed aanvaard te zijn, behoudens tegenbewijs.

Een betwisting over een deel van de factuur ontslaat niet van de betaling van het onbetwiste saldo.

## Artikel 11 — Aansprakelijkheid van de vervoerder
De aansprakelijkheid van de vervoerder voor verlies of beschadiging is beperkt, overeenkomstig artikel 23 CMR, tot 8,33 rekeneenheden, zijnde bijzondere trekkingsrechten, per kilogram ontbrekend brutogewicht.

Bij vertraging kan de vergoeding de vrachtprijs niet overschrijden, overeenkomstig artikel 23, lid 5, CMR.

De vervoerder staat niet in voor indirecte schade, met name productieverlies, winstderving, verlies van cliënteel of contractuele boetes overeengekomen tussen de opdrachtgever en zijn eigen klanten.

## Artikel 12 — Waardeaangifte en bijzonder belang
De opdrachtgever die de grenzen van het vorige artikel wil overschrijden, kan tegen een overeengekomen toeslag de waarde van de goederen aangeven in de zin van artikel 24 CMR of een bijzonder belang bij de aflevering in de zin van artikel 26. De aangifte gebeurt vóór het laden en wordt op de vrachtbrief vermeld.

## Artikel 13 — Voorbehoud en klachten
Zichtbaar verlies of zichtbare schade moet aanleiding geven tot schriftelijk, nauwkeurig en gemotiveerd voorbehoud op de vrachtbrief bij de inontvangstneming.

Niet-zichtbare schade moet schriftelijk worden gemeld binnen zeven dagen na aflevering, zon- en feestdagen niet meegerekend, overeenkomstig artikel 30 CMR.

Vertraging geeft slechts recht op vergoeding indien binnen eenentwintig dagen na de terbeschikkingstelling schriftelijk voorbehoud wordt gemaakt.

## Artikel 14 — Verjaring
Vorderingen uit de vervoersovereenkomst verjaren na één jaar, overeenkomstig artikel 32 CMR. De termijn bedraagt drie jaar in geval van opzet of daarmee gelijkgestelde fout.

## Artikel 15 — Verhindering en overmacht
De vervoerder is niet aansprakelijk voor niet-uitvoering door een gebeurtenis buiten zijn controle, met name een wegblokkade, uitzonderlijke weersomstandigheden, een staking, een overheidsbeslissing of de sluiting van een infrastructuur.

Bij verhindering van het vervoer of de aflevering vraagt de vervoerder instructies en neemt hij, bij gebrek aan nuttig antwoord, de maatregelen die hem het beste lijken in het belang van de rechthebbende, overeenkomstig de artikelen 14 tot 16 CMR.

## Artikel 16 — Onderaanneming
De vervoerder mag de uitvoering geheel of gedeeltelijk toevertrouwen aan een regelmatig vergunde en verzekerde onderaannemer, zonder dat dit zijn verplichtingen tegenover de opdrachtgever wijzigt.

## Artikel 17 — Retentierecht en pand
Alle goederen, documenten en gelden die de vervoerder voor rekening van de opdrachtgever onder zich houdt, strekken tot pand voor de betaling van elke verschuldigde som, ook uit hoofde van eerdere zendingen. De vervoerder beschikt op die goederen over een retentierecht tot volledige betaling.

## Artikel 18 — Persoonsgegevens
De verwerking van persoonsgegevens wordt beschreven in het privacybeleid, bereikbaar via de voettekst van de website. De opdrachtgever waarborgt dat hij de personen wier gegevens hij meedeelt heeft geïnformeerd, met name de contactpersonen voor het laden en lossen.

## Artikel 19 — Gedeeltelijke nietigheid en afstand
De nietigheid van een beding tast de geldigheid van de overige niet aan. Het nietige beding wordt vervangen door een geldige bepaling met een gelijkwaardige economische strekking.

Het niet uitoefenen van een recht op een bepaald ogenblik houdt geen afstand in van de latere uitoefening ervan.

## Artikel 20 — Toepasselijk recht en bevoegdheid
Deze voorwaarden worden beheerst door het Belgisch recht. Onverminderd artikel 31 CMR behoort elk geschil tot de uitsluitende bevoegdheid van de rechtbanken van het gerechtelijk arrondissement Brussel.
TXT;
    }

    private function conditionsEn(): string
    {
        return <<<'TXT'
## Article 1 — Definitions
Carrier: NBLogiTrack SRL. Customer: the party entrusting the performance of a carriage, whether or not it owns the goods. Consignment: all goods entrusted at one time for the same journey. CMR: the Convention on the Contract for the International Carriage of Goods by Road, signed in Geneva on 19 May 1956.

## Article 2 — Scope
These conditions apply to every offer, order and carriage performed by the carrier. They are addressed exclusively to businesses and govern no consumer relationship.

Accepting an order entails acceptance of these conditions. The customer's purchasing conditions do not apply, even if communicated later and left unchallenged, unless expressly accepted in writing by the carrier.

## Article 3 — Legal framework
International carriage is governed by the CMR. National carriage is governed by Belgian law, in particular the Act of 15 July 2013 on the carriage of goods by road. These conditions supplement that framework, in the spirit of the general road transport conditions jointly established by the Belgian professional federations.

In the event of conflict, the mandatory provision of the CMR prevails.

## Article 4 — Offers and formation of the contract
Prices produced by the online simulator are indicative and do not bind the carrier. A written offer is valid for fifteen days unless stated otherwise.

The contract is formed when the carrier confirms the order. A booking recorded in the application constitutes an order once confirmed.

## Article 5 — Customer's obligations
The customer warrants that:
- the goods are packed so as to withstand carriage and normal handling;
- packages are labelled and identifiable;
- the declared weight and volume are accurate;
- documents required for carriage, customs and administrative formalities are supplied in good time and are complete;
- pickup and delivery locations are accessible to the type of vehicle ordered.

The customer is answerable for the consequences of an inaccurate declaration, in particular an overload found during a roadside check.

## Article 6 — Loading, unloading and waiting time
Unless otherwise agreed in writing, loading and unloading are the responsibility of the sender and the consignee respectively. Load securing is carried out under the carrier's responsibility; stowage inside packages is the sender's responsibility.

Two hours of immobilisation are included in the price, both at pickup and at delivery. Beyond that, waiting time is charged at the applicable hourly rate, per commenced half hour.

Where pickup or delivery cannot take place for a reason outside the carrier's control, the costs of return, storage and re-presentation are borne by the customer.

## Article 7 — Dangerous goods
No goods subject to ADR are accepted without prior, complete written declaration stating the UN number, class, packing group and quantities.

Dangerous goods handed over without that declaration may be unloaded, destroyed or rendered harmless without compensation, in accordance with Article 22 CMR. The customer bears the costs and consequences.

## Article 8 — Prices
Prices are exclusive of taxes and established on the basis of the information supplied when ordering. They include carriage and the carrier's liability insurance.

Charged in addition: waiting time beyond the allowance, unforeseen services, exceptional toll costs, empty returns and any additional burden resulting from inaccurate information.

A significant and lasting change in fuel prices may give rise to an adjustment, notified in writing and applicable to carriage performed after the notification.

## Article 8a — Cancellation by the customer
The customer may cancel a shipment from its customer area as long as the goods have not been loaded.

Cancellation is free of charge as long as no vehicle has been assigned to the shipment. Once a vehicle and a driver have been booked for it, cancellation gives rise to a fixed indemnity of twenty-five per cent of the agreed price excluding VAT, with a minimum of fifty euros, without exceeding that price. This indemnity covers the immobilisation of the vehicle and driver; it appears on the invoice for the month of cancellation.

Once the goods have been loaded, the shipment can no longer be cancelled online. A return or diversion is then handled in writing and the services performed are invoiced.

## Article 8b — Right of withdrawal
The carrier's services are reserved for businesses. The right of withdrawal under Articles VI.47 et seq. of the Belgian Code of Economic Law protects consumers; it does not apply to orders placed by a business for the purposes of its activity.

The customer retains the option to cancel a shipment under the conditions of Article 8a.

## Article 9 — Invoicing and payment
Invoices are issued electronically and payable within thirty days of the invoice date, without discount, unless otherwise agreed in writing.

Failing payment on the due date, and without prior notice, the following are due as of right:
- late payment interest at the rate provided for by the Belgian Act of 2 August 2002 on combating late payment in commercial transactions;
- a fixed sum of forty euros for recovery costs, under the same Act;
- beyond that fixed sum, reimbursement of other recovery costs reasonably incurred, on proof, under Article 6 of the same Act.

Non-payment of one invoice on its due date makes all other invoices immediately payable, including those not yet due, and entitles the carrier to suspend ongoing services after written notice.

The carrier may set a payment term and a credit limit for each customer, against which the total, VAT included, of invoices issued and not yet paid is measured. A new shipment cannot be ordered if that total plus its price including VAT would exceed the limit, nor as long as three invoices remain unpaid after their due date; shipments already entrusted are not called into question by this alone.

## Article 10 — Disputing an invoice
Any dispute must be received in writing within ten calendar days of the invoice date, stating precise grounds. After that period, the invoice is presumed accepted, unless proven otherwise.

A dispute concerning part of an invoice does not release the customer from paying the undisputed balance.

## Article 11 — Carrier's liability
The carrier's liability for loss or damage is limited, in accordance with Article 23 CMR, to 8.33 units of account, that is Special Drawing Rights, per kilogram of gross weight short.

In case of delay, compensation may not exceed the carriage charges, in accordance with Article 23(5) CMR.

The carrier is not liable for indirect loss, in particular loss of production, loss of profit, loss of custom, or contractual penalties agreed between the customer and its own clients.

## Article 12 — Declaration of value and special interest
A customer wishing to exceed the limits of the preceding article may, against an agreed surcharge, declare the value of the goods within the meaning of Article 24 CMR or a special interest in delivery within the meaning of Article 26. The declaration must be made before pickup and entered on the consignment note.

## Article 13 — Reservations and claims
Apparent loss or damage must be the subject of written, precise and reasoned reservations entered on the consignment note at the time of receipt.

Non-apparent damage must be notified in writing within seven days of delivery, Sundays and public holidays excluded, in accordance with Article 30 CMR.

Delay gives rise to compensation only if written reservation is sent within twenty-one days of the goods being placed at the disposal of the consignee.

## Article 14 — Limitation period
Actions arising from the contract of carriage are time-barred after one year, in accordance with Article 32 CMR. The period is three years in the case of wilful misconduct or equivalent default.

## Article 15 — Prevention and force majeure
The carrier is not liable for non-performance due to an event beyond its control, in particular a road blockade, exceptional weather, a strike, a decision of the authorities or the closure of an infrastructure.

Where carriage or delivery is prevented, the carrier requests instructions and, failing a useful reply, takes the measures that appear best in the interest of the person entitled to the goods, in accordance with Articles 14 to 16 CMR.

## Article 16 — Subcontracting
The carrier may entrust all or part of the performance to a duly licensed and insured subcontractor, without this altering its obligations towards the customer.

## Article 17 — Right of retention and pledge
All goods, documents and sums held by the carrier on the customer's behalf serve as a pledge for payment of any sum due, including in respect of earlier consignments. The carrier has a right of retention over those assets until payment in full.

## Article 18 — Personal data
The processing of personal data is described in the privacy policy, reachable from the website footer. The customer warrants that it has informed the persons whose data it communicates, in particular pickup and delivery contacts.

## Article 19 — Severability and waiver
The nullity of one clause does not affect the validity of the others. The void clause is replaced by a valid provision of equivalent economic effect.

Failing to exercise a right at a given time does not amount to waiving its later exercise.

## Article 20 — Governing law and jurisdiction
These conditions are governed by Belgian law. Without prejudice to Article 31 CMR, any dispute falls within the exclusive jurisdiction of the courts of the judicial district of Brussels.
TXT;
    }
}
