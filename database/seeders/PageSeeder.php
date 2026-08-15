<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A13 : les pages que tout transporteur doit porter.
 *
 * Leur contenu est calque sur ce que publient les transporteurs belges
 * etablis (Van Mieghem, Jost, les membres Febetra) : cadre normatif
 * cite, limite de responsabilite chiffree, delais de reserve et de
 * prescription, temps d'attente, clause penale, droit de retention. Une
 * page legale de dix lignes ne protege personne.
 *
 * Le corps utilise deux marques seulement, « ## » pour une rubrique et
 * « - » pour une puce. Voir le rendu dans Pages/Show.jsx : rien n'est
 * interprete comme du HTML.
 *
 * Ces textes sont un point de depart credible pour une soutenance, pas
 * un avis juridique. Un transporteur reel les fait relire.
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
                'slug' => 'conditions-generales',
                'titre_fr' => 'Conditions générales de transport',
                'titre_nl' => 'Algemene vervoersvoorwaarden',
                'titre_en' => 'General conditions of carriage',
                'corps_fr' => $this->conditionsFr(),
                'corps_nl' => $this->conditionsNl(),
                'corps_en' => $this->conditionsEn(),
            ],
        ];
    }

    private function mentionsFr(): string
    {
        return <<<'TXT'
## Éditeur du site
NBLogiTrack SRL, société à responsabilité limitée de droit belge.
Siège social : Avenue du Port 86C, 1000 Bruxelles, Belgique.
Numéro d'entreprise et numéro de TVA : BE 0123.456.789.
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
Le site et les données applicatives sont hébergés au sein de l'Union européenne. Le nom et les coordonnées de l'hébergeur sont communiqués sur simple demande écrite.

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

Le présent site s'adresse à des professionnels. La plateforme européenne de règlement en ligne des litiges de consommation n'a donc pas vocation à s'appliquer aux relations qu'il régit.

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
Ondernemingsnummer en btw-nummer: BE 0123.456.789.
Rechtspersonenregister Brussel.
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
De website en de toepassingsgegevens worden binnen de Europese Unie gehost. Naam en gegevens van de hostingpartij worden op eenvoudig schriftelijk verzoek meegedeeld.

## Intellectuele eigendom
De structuur van de website, de teksten, de huisstijl, de illustraties en de broncode zijn auteursrechtelijk beschermd. Elke reproductie, weergave, aanpassing of ontlening, geheel of gedeeltelijk en op welke wijze ook, is verboden zonder voorafgaande schriftelijke toestemming.

Vermelde merken, benamingen en logo's van derden blijven eigendom van hun respectieve houders en worden enkel ter identificatie genoemd.

## Hyperlinks
Links naar websites van derden worden aangeboden voor het gemak van de gebruiker. NBLogiTrack BV heeft geen controle over die websites en wijst elke aansprakelijkheid af voor de inhoud, de beschikbaarheid en het gegevensbeleid ervan.

## Waarde van de gepubliceerde informatie
De prijzen van de tariefsimulator zijn indicatief. Zij berusten op een automatisch berekende afstand en op standaardaannames inzake belading, en houden geen rekening met de bijzonderheden van een zending. Zij vormen geen aanbod, noch een contractuele verbintenis. Alleen een schriftelijk aanbod van NBLogiTrack BV verbindt de vennootschap.

## Beschikbaarheid van de dienst
NBLogiTrack BV stelt alles redelijkerwijs in het werk om de website en de klantenzone toegankelijk te houden. Zij behoudt zich het recht voor de toegang te onderbreken voor onderhoud, zonder dat die onderbreking recht geeft op schadevergoeding.

## Melding van inhoud
Wie meent dat gepubliceerde inhoud zijn rechten schendt, kan dit schriftelijk melden op info@nblogitrack.be, met nauwkeurige aanduiding van de inhoud en het ingeroepen recht.

## Geschillenregeling
Partijen zoeken eerst een minnelijke oplossing. Bij gebreke daarvan zijn de rechtbanken bevoegd die in de algemene voorwaarden zijn aangeduid.

Deze website richt zich tot professionelen. Het Europese platform voor onlinegeschillenbeslechting voor consumenten is dan ook niet van toepassing op de betrekkingen die hij regelt.

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
Company and VAT number: BE 0123.456.789.
Register of Legal Entities, Brussels.
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
The website and application data are hosted within the European Union. The name and contact details of the hosting provider are supplied on written request.

## Intellectual property
The structure of this website, its texts, visual identity, illustrations and source code are protected by copyright. Any reproduction, communication, adaptation or extraction, in whole or in part and by any means, is prohibited without prior written consent.

Third-party trademarks, names and logos remain the property of their respective owners and are mentioned for identification purposes only.

## Hyperlinks
Links to third-party websites are provided for the user's convenience. NBLogiTrack SRL exercises no control over those websites and accepts no liability for their content, availability or data practices.

## Status of published information
Prices shown by the rate simulator are indicative. They rely on an automatically calculated distance and on standard loading assumptions, and do not account for the specific constraints of a consignment. They constitute neither an offer nor a contractual commitment. Only a written offer issued by NBLogiTrack SRL binds the company.

## Service availability
NBLogiTrack SRL takes reasonable steps to keep the website and the customer area accessible. It reserves the right to interrupt access for maintenance, without such interruption giving rise to compensation.

## Reporting content
Anyone who considers that published content infringes their rights may report it in writing to info@nblogitrack.be, precisely identifying the content and the right invoked.

## Dispute resolution
The parties shall seek an amicable solution before any proceedings. Failing that, disputes fall to the courts designated in the general conditions.

This website is addressed to professionals. The European online dispute resolution platform for consumers therefore does not apply to the relationships it governs.

## Governing law
This website and its use are governed by Belgian law.
TXT;
    }

    private function viePriveeFr(): string
    {
        return <<<'TXT'
## Responsable du traitement
NBLogiTrack SRL, Avenue du Port 86C, 1000 Bruxelles, numéro d'entreprise BE 0123.456.789.
Toute question relative aux données personnelles : info@nblogitrack.be

## À qui s'adresse cette politique
Elle décrit le traitement des données de quatre catégories de personnes :
- les personnes de contact des entreprises clientes et des fournisseurs ;
- les conducteurs et le personnel d'exploitation ;
- les expéditeurs et destinataires désignés dans un ordre de transport, qui ne sont pas nécessairement nos clients ;
- les visiteurs du site.

## Données traitées
Personnes de contact : nom, fonction, adresse professionnelle, téléphone, courriel, langue, numéro de TVA de l'entreprise, historique des expéditions, factures et paiements, journaux de connexion.

Conducteurs et personnel : identité, coordonnées, numéro de permis et catégories, dates de validité, certificat ADR, aptitude médicale, données de temps de conduite et de repos issues du tachygraphe, position du véhicule pendant le service.

Expéditeurs et destinataires : nom, adresse d'enlèvement ou de livraison, téléphone de contact, nom de la personne ayant réceptionné la marchandise.

Visiteurs : données strictement nécessaires au fonctionnement du site et à sa sécurité.

## Finalités et bases légales
- Exécuter le contrat de transport, planifier les tournées, suivre les envois — exécution du contrat.
- Établir les factures, recouvrer les créances, tenir la comptabilité — obligation légale et intérêt légitime.
- Respecter les obligations en matière de temps de conduite, de repos et de qualification des conducteurs — obligation légale.
- Établir les documents de transport, dont la lettre de voiture CMR, et les déclarations en douane — obligation légale.
- Assurer la sécurité de l'application, détecter les accès anormaux — intérêt légitime.
- Répondre aux demandes de devis — mesures précontractuelles.

## Géolocalisation des véhicules et données de conduite
La position des véhicules est traitée pour organiser les tournées, informer le client de l'avancement de son envoi et assurer la sécurité des conducteurs et du chargement. Les données de temps de conduite et de repos sont traitées parce que la réglementation européenne l'impose.

Ces traitements suivent la position de l'Autorité de protection des données : ils poursuivent un but professionnel précis, ils sont limités aux heures de service, et ils ne servent pas à une surveillance permanente et systématique des conducteurs, laquelle serait disproportionnée. Les conducteurs en sont informés préalablement et individuellement.

## Destinataires et sous-traitants
Les données ne sont ni vendues, ni louées, ni échangées. Elles sont communiquées, dans la limite du nécessaire :
- à l'hébergeur de l'application, établi dans l'Union européenne ;
- au prestataire de paiement en ligne, pour les seules données nécessaires à la transaction ;
- à l'opérateur de la taxe kilométrique et aux exploitants d'infrastructures à péage ;
- aux sous-traitants de transport lorsqu'un envoi leur est confié ;
- au cabinet comptable, à l'assureur et, le cas échéant, au conseil juridique ;
- aux administrations lorsque la loi l'impose, notamment en matière fiscale, douanière et sociale.

Chaque sous-traitant est lié par un contrat conforme à l'article 28 du RGPD.

## Transferts hors de l'Union européenne
Les données sont traitées au sein de l'Union européenne. Un transfert vers un pays tiers n'a lieu que lorsqu'une livraison l'exige, et sur la base des garanties prévues au chapitre V du RGPD.

## Durées de conservation
- Pièces comptables et factures : sept ans, conformément au Code de la TVA.
- Documents de transport et lettres de voiture : cinq ans.
- Données des conducteurs relatives au temps de conduite : la durée imposée par la réglementation sociale européenne.
- Journaux d'activité de l'application, qui enregistrent la date, l'utilisateur, l'action et l'adresse IP : douze mois.
- Demandes de devis restées sans suite : deux ans.
- Compte client : la durée de la relation commerciale, puis les délais de prescription applicables.

## Sécurité
L'accès à l'application est nominatif et limité par le rôle de chacun. Les mots de passe ne sont jamais conservés en clair. Les actions sensibles sont journalisées. Les sauvegardes sont chiffrées et conservées au sein de l'Union européenne.

## Décision automatisée
Aucune décision produisant des effets juridiques n'est prise sur le seul fondement d'un traitement automatisé. Le calcul tarifaire et les propositions d'affectation d'un véhicule ou d'un conducteur sont des aides à la décision : un planificateur valide.

## Témoins de connexion
Le site utilise les seuls témoins nécessaires à son fonctionnement et à la sécurité de la session. Aucun témoin publicitaire ni aucun traceur de mesure d'audience tiers n'est déposé.

## Vos droits
Vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation, d'opposition et de portabilité. Ces droits s'exercent par écrit à info@nblogitrack.be. Une réponse est apportée dans le mois, prorogeable de deux mois si la demande est complexe.

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
NBLogiTrack BV, Havenlaan 86C, 1000 Brussel, ondernemingsnummer BE 0123.456.789.
Vragen over persoonsgegevens: info@nblogitrack.be

## Voor wie geldt dit beleid
Het beschrijft de verwerking van gegevens van vier categorieën personen:
- contactpersonen van klanten en leveranciers;
- chauffeurs en exploitatiepersoneel;
- afzenders en geadresseerden vermeld in een vervoersopdracht, die niet noodzakelijk onze klant zijn;
- bezoekers van de website.

## Verwerkte gegevens
Contactpersonen: naam, functie, professioneel adres, telefoon, e-mail, taal, btw-nummer van de onderneming, geschiedenis van zendingen, facturen en betalingen, aanmeldlogboeken.

Chauffeurs en personeel: identiteit, contactgegevens, rijbewijsnummer en categorieën, geldigheidsdata, ADR-certificaat, medische geschiktheid, rij- en rusttijden uit de tachograaf, positie van het voertuig tijdens de dienst.

Afzenders en geadresseerden: naam, ophaal- of leveradres, contacttelefoon, naam van wie de goederen in ontvangst nam.

Bezoekers: uitsluitend de gegevens die nodig zijn voor de werking en de beveiliging van de website.

## Doeleinden en rechtsgronden
- De vervoersovereenkomst uitvoeren, ritten plannen, zendingen opvolgen — uitvoering van de overeenkomst.
- Facturen opmaken, schulden invorderen, boekhouding voeren — wettelijke verplichting en gerechtvaardigd belang.
- De verplichtingen inzake rij- en rusttijden en vakbekwaamheid naleven — wettelijke verplichting.
- Vervoersdocumenten opmaken, waaronder de CMR-vrachtbrief, en douaneaangiften — wettelijke verplichting.
- De toepassing beveiligen en afwijkende toegang opsporen — gerechtvaardigd belang.
- Offerteaanvragen beantwoorden — precontractuele maatregelen.

## Geolokalisatie van voertuigen en rijgegevens
De positie van de voertuigen wordt verwerkt om ritten te organiseren, de klant over zijn zending te informeren en de veiligheid van chauffeurs en lading te verzekeren. Rij- en rusttijden worden verwerkt omdat de Europese regelgeving dat oplegt.

Deze verwerkingen volgen het standpunt van de Gegevensbeschermingsautoriteit: zij streven een welbepaald professioneel doel na, zijn beperkt tot de diensturen en dienen niet voor een permanent en systematisch toezicht op de chauffeurs, wat onevenredig zou zijn. Chauffeurs worden vooraf en individueel geïnformeerd.

## Ontvangers en verwerkers
Gegevens worden niet verkocht, verhuurd of geruild. Zij worden meegedeeld, beperkt tot het noodzakelijke:
- aan de hostingpartij van de toepassing, gevestigd in de Europese Unie;
- aan de aanbieder van onlinebetalingen, enkel de gegevens die de transactie vereist;
- aan de exploitant van de kilometerheffing en aan tolinfrastructuurbeheerders;
- aan onderaannemers in het vervoer wanneer een zending hen wordt toevertrouwd;
- aan het boekhoudkantoor, de verzekeraar en, in voorkomend geval, de juridisch raadsman;
- aan de overheid wanneer de wet dat oplegt, met name inzake fiscaliteit, douane en sociale zekerheid.

Elke verwerker is gebonden door een overeenkomst conform artikel 28 AVG.

## Doorgifte buiten de Europese Unie
Gegevens worden binnen de Europese Unie verwerkt. Doorgifte naar een derde land gebeurt enkel wanneer een levering dat vereist, op basis van de waarborgen van hoofdstuk V AVG.

## Bewaartermijnen
- Boekhoudkundige stukken en facturen: zeven jaar, conform het Btw-wetboek.
- Vervoersdocumenten en vrachtbrieven: vijf jaar.
- Gegevens over rijtijden van chauffeurs: de termijn opgelegd door de Europese sociale regelgeving.
- Activiteitenlogboeken van de toepassing, met datum, gebruiker, actie en IP-adres: twaalf maanden.
- Offerteaanvragen zonder gevolg: twee jaar.
- Klantaccount: de duur van de handelsrelatie, vervolgens de toepasselijke verjaringstermijnen.

## Beveiliging
De toegang tot de toepassing is persoonsgebonden en beperkt tot de rol van elkeen. Wachtwoorden worden nooit in leesbare vorm bewaard. Gevoelige handelingen worden gelogd. Back-ups zijn versleuteld en worden binnen de Europese Unie bewaard.

## Geautomatiseerde besluitvorming
Er wordt geen besluit met rechtsgevolgen genomen op de enkele grondslag van een geautomatiseerde verwerking. De tariefberekening en de voorstellen tot toewijzing van een voertuig of chauffeur zijn hulpmiddelen: een planner beslist.

## Cookies
De website gebruikt uitsluitend de cookies die nodig zijn voor de werking en de beveiliging van de sessie. Er worden geen reclamecookies of externe analysetrackers geplaatst.

## Uw rechten
U beschikt over een recht op inzage, verbetering, wissing, beperking, bezwaar en overdraagbaarheid. Deze rechten worden schriftelijk uitgeoefend op info@nblogitrack.be. Een antwoord volgt binnen de maand, verlengbaar met twee maanden bij een complexe aanvraag.

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
NBLogiTrack SRL, Avenue du Port 86C, 1000 Brussels, company number BE 0123.456.789.
Any question regarding personal data: info@nblogitrack.be

## Who this policy concerns
It describes the processing of data relating to four categories of people:
- contact persons at customer and supplier companies;
- drivers and operations staff;
- senders and consignees named in a transport order, who are not necessarily our customers;
- website visitors.

## Data processed
Contact persons: name, job title, business address, phone, email, language, company VAT number, shipment history, invoices and payments, sign-in logs.

Drivers and staff: identity, contact details, licence number and categories, validity dates, ADR certificate, medical fitness, driving and rest time data from the tachograph, vehicle position during service.

Senders and consignees: name, pickup or delivery address, contact phone, name of the person who received the goods.

Visitors: strictly the data required for the website to work and remain secure.

## Purposes and legal bases
- Performing the transport contract, planning rounds, tracking shipments — performance of the contract.
- Issuing invoices, recovering debts, keeping accounts — legal obligation and legitimate interest.
- Complying with driving time, rest period and driver qualification rules — legal obligation.
- Producing transport documents, including the CMR consignment note, and customs declarations — legal obligation.
- Securing the application and detecting abnormal access — legitimate interest.
- Answering quotation requests — pre-contractual measures.

## Vehicle geolocation and driving data
Vehicle positions are processed to organise rounds, keep customers informed of their shipment and ensure the safety of drivers and cargo. Driving and rest time data are processed because European regulation requires it.

This processing follows the position of the Belgian Data Protection Authority: it pursues a defined professional purpose, is limited to service hours, and is not used for permanent, systematic monitoring of drivers, which would be disproportionate. Drivers are informed beforehand and individually.

## Recipients and processors
Data is never sold, rented or exchanged. It is disclosed, limited to what is necessary:
- to the application's hosting provider, established in the European Union;
- to the online payment provider, for the data the transaction requires only;
- to the kilometre charge operator and to toll infrastructure operators;
- to transport subcontractors when a consignment is entrusted to them;
- to the accounting firm, the insurer and, where applicable, legal counsel;
- to public authorities where the law so requires, in particular in tax, customs and social security matters.

Every processor is bound by a contract compliant with Article 28 GDPR.

## Transfers outside the European Union
Data is processed within the European Union. Transfer to a third country occurs only where a delivery requires it, on the basis of the safeguards provided for in Chapter V GDPR.

## Retention periods
- Accounting records and invoices: seven years, under the Belgian VAT Code.
- Transport documents and consignment notes: five years.
- Driver driving time data: the period imposed by European social regulation.
- Application activity logs, recording date, user, action and IP address: twelve months.
- Quotation requests left without follow-up: two years.
- Customer account: the duration of the commercial relationship, then the applicable limitation periods.

## Security
Access to the application is personal and limited by each person's role. Passwords are never stored in readable form. Sensitive actions are logged. Backups are encrypted and kept within the European Union.

## Automated decision-making
No decision producing legal effects is taken on the sole basis of automated processing. Rate calculation and vehicle or driver assignment suggestions are decision aids: a planner validates them.

## Cookies
The website uses only the cookies required for its operation and session security. No advertising cookies and no third-party analytics trackers are placed.

## Your rights
You have the right of access, rectification, erasure, restriction, objection and portability. These rights are exercised in writing at info@nblogitrack.be. A reply is given within one month, extendable by two months where the request is complex.

Some rights have limits: data appearing on an invoice or a consignment note cannot be erased before the statutory retention period expires.

## Complaint
You may lodge a complaint with the Belgian Data Protection Authority, Rue de la Presse 35, 1000 Brussels — contact@apd-gba.be — without prejudice to any judicial remedy.

## Changes
This policy may be amended. The date of the last update appears at the bottom of this page.
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

## Article 9 — Facturation et paiement
Les factures sont émises par voie électronique et payables à trente jours de date de facture, sans escompte, sauf convention écrite contraire.

À défaut de paiement à l'échéance, et sans mise en demeure préalable, sont dus de plein droit :
- un intérêt de retard au taux prévu par la loi du 2 août 2002 concernant la lutte contre le retard de paiement dans les transactions commerciales ;
- une indemnité forfaitaire de quarante euros pour frais de recouvrement, conformément à la même loi ;
- une indemnité complémentaire de dix pour cent du montant impayé, avec un minimum de cent cinquante euros, à titre de dommage forfaitaire couvrant les frais administratifs et de suivi.

Le non-paiement d'une facture à son échéance rend immédiatement exigibles toutes les autres factures, même non échues, et autorise le transporteur à suspendre les prestations en cours après notification écrite.

## Article 10 — Contestation d'une facture
Toute contestation doit parvenir par écrit dans les dix jours calendrier de la date de facture, avec l'indication précise des motifs. Passé ce délai, la facture est réputée acceptée.

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

## Artikel 9 — Facturatie en betaling
Facturen worden elektronisch uitgereikt en zijn betaalbaar binnen dertig dagen na factuurdatum, zonder korting, behoudens andersluidende schriftelijke afspraak.

Bij niet-betaling op de vervaldag zijn van rechtswege en zonder ingebrekestelling verschuldigd:
- een verwijlintrest tegen de rentevoet van de wet van 2 augustus 2002 betreffende de bestrijding van de betalingsachterstand bij handelstransacties;
- een forfaitaire vergoeding van veertig euro voor invorderingskosten, conform diezelfde wet;
- een bijkomende vergoeding van tien procent van het onbetaalde bedrag, met een minimum van honderdvijftig euro, als forfaitaire schade voor administratie- en opvolgingskosten.

De niet-betaling van één factuur op haar vervaldag maakt alle andere facturen onmiddellijk opeisbaar, ook de niet-vervallen, en machtigt de vervoerder om lopende prestaties op te schorten na schriftelijke kennisgeving.

## Artikel 10 — Betwisting van een factuur
Elke betwisting moet schriftelijk toekomen binnen tien kalenderdagen na factuurdatum, met nauwkeurige opgave van de redenen. Na die termijn wordt de factuur geacht te zijn aanvaard.

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

## Article 9 — Invoicing and payment
Invoices are issued electronically and payable within thirty days of the invoice date, without discount, unless otherwise agreed in writing.

Failing payment on the due date, and without prior notice, the following are due as of right:
- late payment interest at the rate provided for by the Belgian Act of 2 August 2002 on combating late payment in commercial transactions;
- a fixed sum of forty euros for recovery costs, under the same Act;
- an additional indemnity of ten per cent of the unpaid amount, with a minimum of one hundred and fifty euros, as liquidated damages covering administrative and follow-up costs.

Non-payment of one invoice on its due date makes all other invoices immediately payable, including those not yet due, and entitles the carrier to suspend ongoing services after written notice.

## Article 10 — Disputing an invoice
Any dispute must be received in writing within ten calendar days of the invoice date, stating precise grounds. After that period, the invoice is deemed accepted.

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
