# NBLogiTrack — Gestion de transport (Transport Management System)

> Application web de gestion de transport développée dans le cadre de mon épreuve intégrée à TECHGEST, Institut des Carrières Commerciales de Bruxelles.

**Auteur :** Soufiane Achraa — Épreuve intégrée 2025-2026 — TECHGEST, Institut des Carrières Commerciales de Bruxelles  
**Version :** beta (en développement)  
**En ligne :** <https://nblogitrack.onrender.com> (démonstration, offre gratuite de Render : le premier chargement après une pause prend près d'une minute)  
**Stack :** Laravel · React · Inertia · Vite · Tailwind CSS · PostgreSQL

---

## Objectif

NBLogiTrack suit une expédition de bout en bout, de la commande du client jusqu'au paiement de la facture :

1. L'**entreprise** s'inscrit ; son identité est vérifiée auprès des registres officiels européens
2. Un **administrateur** valide la demande ; l'entreprise reçoit son e-mail d'activation
3. Le **client** passe une commande de transport et obtient une estimation de prix en temps réel
4. Le **planificateur** affecte chaque commande à un véhicule et à un chauffeur ; l'application contrôle, sur toute la durée de la mission, le permis qu'exige le véhicule, les documents du chauffeur, la charge, l'équipement et la certification pour matières dangereuses (ADR), les congés et les temps de conduite
5. Le **chauffeur** consulte ses missions et confirme la livraison
6. Dès la livraison, la **facture** est générée en PDF et au format électronique européen, envoyée par courriel, puis réglée à son échéance, en ligne ou par virement

**Règle métier — Suivi public :** chaque expédition reçoit un numéro de suivi unique, consultable sans compte depuis une page publique, à l'aide d'un code d'accès transmis au client.

**Règle métier — Validation obligatoire :** une entreprise inscrite ne peut pas se connecter tant qu'un administrateur ne l'a pas validée. Une société en faillite, en liquidation ou en réorganisation judiciaire est refusée automatiquement.

---

## Rôles

| Rôle | Accès |
|---|---|
| **Client** | Une entreprise, plusieurs comptes : le gestionnaire de l'entreprise invite ses collègues et choisit leur rôle (gestionnaire, commandes, comptabilité). Les commandes passent, suivent et annulent les expéditions ; la comptabilité consulte et règle les factures |
| **Chauffeur** | Consulte ses missions, confirme l'enlèvement puis la livraison, qui émet aussitôt la facture du client |
| **Planificateur** | Affecte un véhicule et un chauffeur à chaque commande, réaffecte en cas d'imprévu |
| **Administrateur** | Valide les entreprises clientes et fixe leur délai de paiement et leur plafond de crédit, accorde les accès à l'API, consulte le journal d'activité, gère la flotte et les utilisateurs |
| **Visiteur** | Suit une expédition via son numéro et son code, sans authentification |

---

## Fonctionnalités

| Domaine | Description | État |
|---|---|---|
| **Comptes & rôles** | Inscription, connexion, autorisations par rôle (Breeze + Gate). Adresse e-mail confirmée par lien avant l'accès aux écrans (le profil reste ouvert pour corriger une adresse mal saisie) ; mots de passe de 12 à 72 caractères ; essais limités par adresse IP et par compte, sans qu'un inconnu puisse bloquer le titulaire depuis ses adresses habituelles ; réponses identiques, y compris en durée, qu'une adresse ait un compte ou non ; « se souvenir de moi » limité à 30 jours | ✅ v0.1 alpha, complétée en v0.3 beta |
| **Vérification des entreprises** | Contrôle du numéro de taxe sur la valeur ajoutée (TVA) auprès du service européen VIES, lecture des registres belge et français, identifiant sur le réseau Peppol des 27 pays de l'Union, de la Suisse, de la Norvège et du Royaume-Uni | ✅ v0.2 beta |
| **Validation des inscriptions** | Examen par l'administrateur, e-mails d'activation et de refus motivé | ✅ v0.2 beta |
| **Création de commande** | Saisie guidée de l'adresse en entonnoir (pays, ville, code postal, rue, numéro : chaque niveau limite le suivant ; un numéro que la cartographie ne connaît pas est accepté et localisé à la rue), distance routière réelle, estimation du prix en temps réel calculée par le serveur : Éco ≤ Standard ≤ Express, et le groupage ne coûte jamais plus qu'un camion dédié. Pour les marchandises souvent soumises à l'ADR, le client déclare explicitement si son envoi l'est ; une commande qu'aucun camion de la flotte ne peut prendre (poids, volume, équipement ADR et hayon réunis) est signalée dès la saisie, sans prix affiché, et refusée à l'enregistrement comme par l'API ; le volume se saisit. Les livraisons vers la Grèce, dont GeoNames ne publie pas les codes postaux, voient leur localité vérifiée en ligne (Photon). Bruxelles propose les codes postaux de ses dix-neuf communes ; les rues du quartier se chargent dès le choix de la localité et se proposent dès la première lettre | ✅ v0.2 beta, complétée en v0.3 beta |
| **Enlèvements hors de Belgique et fret retour** | Import vers la Belgique commandé en ligne depuis 21 pays de l'Union (15 à l'heure de Bruxelles, 6 à leur heure locale : pays baltes, Bulgarie, Roumanie, Portugal), au prix de l'export miroir (grilles du pays étranger). Suisse, Royaume-Uni, Norvège, Irlande, Finlande, Grèce, Chypre, Malte, îles et territoires hors TVA, et trajets entre deux pays étrangers : sur devis, une contrainte en base interdisant ces derniers. Premier enlèvement possible calculé à l'heure : route depuis le dépôt, pauses et repos du règlement 561/2006, heures de quai, jours fériés du pays et de sa région (Länder, Alsace-Moselle), interdictions de circuler. Expéditeur au lieu de chargement obligatoire. Tarif fret retour : quand un de nos camions revient de la région à la date demandée, 15 % de remise sur le prix de ligne (réglable de 0 à 25 %), jamais sous le tarif national belge ; le prix est recalculé sous verrou et comparé au prix affiché. Le planificateur voit les binômes qui livrent à côté, les frets retour possibles sur chaque mission à l'étranger, ou le départ du dépôt à prévoir ; un camion ne se voit pas affecter un chargement qu'il ne peut pas rejoindre à temps | ✅ v0.3 beta |
| **Catalogue des ordres** | Liste, recherche par colonne, filtrage selon le rôle, fiche détaillée d'une expédition | ✅ v0.1 alpha, fiche détaillée en v0.2 beta |
| **Annulation par le client** | Gratuite avant l'affectation d'un camion, indemnité de 25 % du prix (50 € minimum) ensuite, portée sur la facture du mois ; impossible en ligne une fois la marchandise chargée (article 8 bis des conditions générales) | ✅ v0.3 beta |
| **Planification** | Affectation véhicule et chauffeur contrôlée par un service unique, sur toute la durée de la mission : permis exigé par le véhicule (un tracteur de 44 t exige le CE, quelle que soit sa carrosserie), documents du chauffeur (permis, visite médicale ; code 95 et carte tachygraphe au-delà de 3,5 t), véhicule équipé et chauffeur certifié ADR jusqu'au dernier jour, charge et volume cumulés en groupage, contrôle technique, congés et immobilisations datés, chevauchements, temps de conduite (9 h par jour, 56 h par semaine, repos du septième jour). L'écran grise, à la date de la mission, les camions et chauffeurs qui ne conviennent pas (documents, permis, certification et équipement ADR, capacité, volume, hayon, contrôle technique, congés et immobilisations) ; les chevauchements, la charge et le volume cumulés du groupage et les temps de conduite se vérifient à l'enregistrement de l'affectation ; un refus nomme la mission qui bloque le camion ou le chauffeur ; une mission devenue non conforme (document expiré, fiche corrigée) est signalée, et le chauffeur ne peut plus la prendre en charge ; une mission « en route » depuis plus d'une semaine, sans livraison enregistrée, est signalée. Transitions de statut (en attente, affecté, en cours, livré, annulé) centralisées et atomiques, affectation sous verrou : deux planificateurs ne réservent pas le même camion ; une marchandise chargée ne revient jamais en attente, elle se réaffecte à un autre camion ; la livraison garde l'heure, le réceptionnaire et les réserves | ✅ v0.2 beta, complétée en v0.3 beta |
| **Incidents en mission** | Le chauffeur signale un accident, une panne ou une marchandise endommagée, avec sa description et sa position, et indique si la marchandise a souffert. Accident ou panne : le camion est immobilisé, ni chargement ni livraison possibles ; le chauffeur reprend la route une fois le camion réparé ou demande un autre véhicule, que le planificateur lui affecte. Camion et marchandise endommagés : le chauffeur attend les consignes, le planificateur décide avec le client (annuler, envoyer un autre camion ou autoriser la reprise). Marchandise endommagée : livraison avec réserves déjà remplies. L'administration et la planification reçoivent le détail par courriel (chauffeur et téléphone, camion, adresses, position) et une alerte au tableau de bord ; le client est prévenu d'un retard possible, sans le détail interne | ✅ v0.3 beta |
| **Avis au client** | Courriels dans la langue du client à chaque étape : commande enregistrée, camion affecté, incident en route, livraison effectuée (date, réceptionnaire et réserves), facture | ✅ v0.2 beta, complétée en v0.3 beta |
| **Suivi public** | Consultation d'un envoi (numéro + code), état de livraison | ✅ v0.1 alpha |
| **Journal d'activité** | Date, utilisateur, type d'action et adresse IP, avec filtres | ✅ v0.2 beta |
| **Tableau de bord** | Indicateurs clés et derniers ordres | ✅ v0.1 alpha |
| **Gestion de la flotte** | Véhicules et chauffeurs, contrôle technique, permis exigé par chaque véhicule et équipement ADR, permis, code 95, carte tachygraphe et certificat ADR des chauffeurs, statut d'emploi, départs programmés, congés et passages au garage datés à l'avance | ✅ v0.2 beta, complétée en v0.3 beta |
| **Facturation** | Une facture émise et envoyée dès la livraison d'une mission, que le chauffeur la confirme ou que le planificateur l'enregistre : le transport et les suppléments déjà posés (attente, manutention). Le 1er du mois, une facture par client reprend le reste : suppléments posés après la livraison et indemnités d'annulation. Identité de l'acheteur figée à l'émission, catégorie de TVA par ligne (21 %, autoliquidation intracommunautaire, hors champ hors Union), avoirs numérotés à part qui annulent une facture, avec ou sans refacturation immédiate, communication structurée belge, PDF et fichier UBL au format Peppol BIS 3.0 (norme européenne EN 16931), envoi par courriel avec les deux fichiers en pièces jointes. Bouton « Facturer maintenant » pour l'administrateur, en solution de repli : émet et envoie tout de suite ce qui reste à facturer (supplément tardif, indemnité d'annulation, facture en échec), mois en cours compris, sans attendre le 1er du mois. La transmission sur le réseau Peppol demande un point d'accès certifié, pas encore raccordé | ✅ v0.2 beta, refondue en v0.3 beta |
| **Paiement** | Paiements enregistrés un à un (virement, en ligne, autre), paiements partiels et reste dû ; règlement en ligne du solde (Stripe), enregistré dès le retour du client ou par la notification signée, jamais deux fois, paiement différé (SEPA) suivi, trop-perçu signalé ; rapport de TVA qui déduit les avoirs | ✅ v0.2 beta, complétée en v0.3 beta |
| **Encours et plafond de crédit** | Paiement à terme, comme en B2B : délai propre à chaque entreprise (30, 45 ou 60 jours, fin de mois) et plafond de crédit facultatif (vide : sans limite), réglés par l'administrateur, qui voit l'encours de chaque client, c'est-à-dire ses factures émises et non réglées, TTC (les expéditions pas encore facturées n'y entrent pas). Une ou deux factures en retard : rappel au client, qui commande encore ; trois factures en retard, ou un encours qui, augmenté de la commande TTC, dépasserait le plafond : nouvelle commande refusée, sur le site comme par l'API ou par la transformation d'un devis, contrôlée sous le verrou de commande (article 9 des conditions générales) | ✅ ajouté depuis la v0.3 beta |
| **Multilingue** | Français, néerlandais et anglais partout : écrans, messages, courriels et facture PDF, dans la langue de chaque utilisateur ; écran d'administration des traductions, et un test qui refuse toute clé sans ses trois traductions | ✅ v0.2 beta, complétée en v0.3 beta |
| **Achats et TVA** | Factures de carburant et de péage, synthèse de TVA mensuelle | ✅ v0.2 beta |
| **Devis** | Demande publique en cinq étapes, avec barre de progression et brouillon gardé dans le navigateur : société (numéro de TVA vérifié dans toute l'Union par VIES, en Suisse par le registre UID, en Norvège par Brønnøysund, au Royaume-Uni par HMRC si une application est déclarée ; format et clé de contrôle vérifiés avant tout appel ; raison sociale, adresse du siège, forme juridique et dirigeant repris du registre, forme juridique et secteur complétés par les registres nationaux gratuits (BCE, Recherche d'entreprises française, ARES tchèque, PRH finlandais, KRS polonais, ANAF roumaine) ou, à défaut, lus dans la raison sociale ; ce que le registre du pays ne publie pas est signalé ; nouvel essai automatique si le registre est saturé), EORI exigé pour la Suisse, le Royaume-Uni et la Norvège, adresse de facturation, contact et moyen de rappel, contacts, horaires, quai, rendez-vous et accès difficiles à l'enlèvement et à la livraison, colis détaillés (poids et volume calculés), valeur déclarée, température dirigée, numéro ONU et classe ADR, volume mensuel, budget, pièces jointes privées. Le personnel voit tout le détail, télécharge les pièces jointes et transforme le devis en commande tarifée pour l'entreprise cliente | ✅ v0.2 beta (demande simple), refondue en v0.3 beta |
| **Audience et référencement** | Mesure d'audience propre au site public, seulement avec l'accord du visiteur (bandeau des témoins, refusée par défaut) : pages vues, arrivées et leur provenance (moteurs, réseaux, campagnes `utm_*`), demandes de devis, inscriptions et simulations, sans témoin de suivi ni adresse IP, effacées après 13 mois ; écran « Audience du site » pour l'administrateur. Titre, description et aperçu de partage (Open Graph) posés par le serveur, pages privées en `noindex`, `sitemap.xml` trilingue avec `hreflang`, `robots.txt`, fiche de l'entreprise pour les moteurs (schema.org) | ✅ v0.3 beta |
| **Suivi géolocalisé** | Jalons horodatés (enlèvement, livraison), conservés un an, et position en direct, activable par mission, effacée sept jours après la livraison ou l'annulation ; carte vectorielle aux couleurs de Waze (itinéraire routier, péages, camion en route), avec repli sur la carte OpenStreetMap standard | ✅ v0.2 beta, complétée en v0.3 beta |
| **Interface de programmation (API REST)** | Interface versionnée pour les partenaires, clés révocables, limitation de débit par clé, dépôt idempotent, journal des appels (refus de limite compris) ; documentation dans [docs/API.md](docs/API.md) et dans Swagger UI (`/api/docs`, spécification [docs/openapi.yaml](docs/openapi.yaml)) ; le gestionnaire d'une entreprise cliente demande son accès en ligne, et la clé accordée ne s'affiche qu'une fois | ✅ v0.2 beta, complétée en v0.3 beta (documentation Swagger, demande d'accès et journal des refus de limite ajoutés depuis) |
| **Pages publiques** | Mentions légales, confidentialité et conditions générales, modifiables sans redéploiement | ✅ v0.2 beta |
| **Conformité RGPD** | Registre des traitements et durées de conservation du règlement général sur la protection des données, appliqués par tâches planifiées : factures, avoirs et paiements gardés sept ans à compter du 1ᵉʳ janvier qui suit leur année (Code de la TVA, art. 60 ; Code de droit économique, art. III.88), puis effacés avec les ordres qu'ils justifient | ✅ v0.2 beta, complétée depuis (conservation légale des pièces) |
| **Mise en ligne** | Application en ligne sur Render (conteneur Docker), base PostgreSQL hébergée chez Supabase, migrations et traductions synchronisées à chaque démarrage. Courriels réels par le relais SMTP de Brevo, les adresses inventées du jeu de démonstration étant retenues. Codes postaux importés au démarrage du serveur, repris pays par pays après une coupure (voir *Déploiement en ligne*) | ✅ v0.3 beta |
| **Tests et intégration continue** | 693 tests sur PostgreSQL, exécutés à chaque proposition de fusion et à chaque envoi sur `main`, avec `composer audit` et `npm audit` : côté Composer, tout avis de sécurité publié, quelle que soit sa gravité, et tout paquet abandonné font échouer la CI ; côté npm, une faille haute ou critique, sauf un avis examiné et documenté dans `.github/audit-npm.mjs` (`braces`, sans version corrigée, utilisé seulement par Tailwind 3 à la compilation). Actions GitHub épinglées par empreinte, jeton en lecture seule ; Dependabot configuré, propositions de mise à jour suspendues jusqu'après la présentation (alertes de sécurité actives) | ✅ v0.1 alpha (style et compilation), tests en v0.2 beta, audits et épinglage en v0.3 beta |
| **Preuve de livraison** | Signature du destinataire depuis l'espace chauffeur | 🔜 à venir |

Version où chaque fonctionnalité est apparue, puis, le cas échéant, celle qui l'a complétée : **v0.1 alpha** (8 août 2026), **v0.2 beta** (17 août 2026), **v0.3 beta** (mise en ligne et opérations terrain, publiée le 30 septembre 2026 ; étiquette reposée le 3 octobre pour y inclure la facture émise à la livraison).

---

## Sources de données publiques

L'application interroge plusieurs services ouverts, sans clé d'accès :

| Service | Usage |
|---|---|
| **VIES**, système d'échange d'informations sur la TVA (Commission européenne) | Validation du numéro de TVA, raison sociale et adresse du siège |
| **Banque-Carrefour des Entreprises** (Belgique) | Dirigeant, secteur d'activité, situation juridique |
| **Recherche d'entreprises** (France) | Dirigeant, code d'activité NACE, état administratif |
| **GeoNames** | Villes et codes postaux des pays desservis (la Grèce, que GeoNames ne publie pas, est vérifiée par Photon) |
| **Photon** et **Overpass** (OpenStreetMap) | Villes, rues et numéros de police existants ; péages le long de l'itinéraire (Overpass) |
| **Base Adresse Nationale** (France) et **PDOK** (Pays-Bas) | Rues françaises et néerlandaises |
| **OSRM** (Open Source Routing Machine) | Distance routière et itinéraire entre deux adresses |
| **Yasumi** (bibliothèque libre, MIT) | Jours fériés des pays d'enlèvement et de leurs régions |
| **OpenFreeMap** | Fond de carte vectoriel (tuiles OpenMapTiles), sans clé ni quota |
| **OpenStreetMap** | Carte de repli, pour les navigateurs sans WebGL 2 ou quand OpenFreeMap ne répond pas |

---

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end | Laravel 12 (PHP 8.2+) |
| Front-end | React 19 + Inertia 3 + Vite |
| Mise en forme | Tailwind CSS |
| Cartographie | Leaflet et MapLibre GL (fond vectoriel) |
| Base de données | PostgreSQL 16 |
| Authentification | Laravel Breeze (session) |
| Messagerie | Mailpit en développement, Brevo (SMTP) en production |
| Paiement | Stripe |
| Facturation électronique | UBL Peppol BIS 3.0 — norme européenne EN 16931 (fichier généré ; transmission par point d'accès à raccorder) |
| Tests | PHPUnit sur PostgreSQL |
| Intégration continue | GitHub Actions — style, audit des dépendances, compilation et tests |

---

## Installation

### Prérequis

PHP 8.2+ (extensions pdo_pgsql, intl et zip), Composer, Node.js 22+ et PostgreSQL 16.

> **Certificats HTTPS.** Les registres européens sont interrogés en HTTPS. Si `curl.cainfo` et `openssl.cafile` ne sont pas renseignés dans votre `php.ini`, les appels échouent sans message explicite. Vérifiez avec `php -r "var_dump(ini_get('curl.cainfo'));"`.

### Étapes

```bash
git clone https://github.com/soufianeach-DEV/nblogitrack.git
cd nblogitrack

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Créez la base PostgreSQL, renseignez ses accès dans `.env`, puis créez les tables et le jeu de démonstration :

```bash
php artisan migrate --seed
```

Après une mise à jour du code, lancez `composer install`, `npm install` puis `php artisan migrate` : il synchronise aussi le dictionnaire des traductions (nouvelles clés ajoutées, textes retouchés à la main dans l'écran Traductions conservés) et vide son cache, quand au moins une migration est jouée. La commande `php artisan traductions:synchroniser` fait la même chose seule.

Une base créée avant le contrôle des affectations garde ses données, mais les règles s'appliquent aussitôt : renseignez la date de fin du certificat ADR des chauffeurs certifiés (sans elle, aucune marchandise dangereuse ne leur est confiée) ainsi que l'échéance du code 95 et de la carte tachygraphe de chaque chauffeur (sans elles, aucun véhicule de plus de 3,5 t ne lui est confié), et cochez « Équipé ADR » sur les véhicules concernés (les citernes le sont d'office). `php artisan migrate:fresh --seed` repart d'un jeu de démonstration cohérent, en effaçant les données.

Pour vérifier les numéros de TVA britanniques, déclarez une application sur le portail développeurs de HMRC et renseignez `HMRC_CLIENT_ID` et `HMRC_CLIENT_SECRET` ; sans elles, seul le format est contrôlé.

Les pages de l'entreprise sur les réseaux sociaux se déclarent dans `RESEAU_LINKEDIN`, `RESEAU_FACEBOOK` et `RESEAU_INSTAGRAM` : elles apparaissent au pied du site public et dans la fiche lue par les moteurs de recherche.

La remise fret retour se règle par la variable `FRET_RETOUR_REMISE` (0,15 par défaut, entre 0 et 0,25 ; 0 la désactive) ; les pays d'enlèvement, les heures de quai et de prise de service se trouvent dans `config/fret.php`.

Importez enfin les codes postaux européens, indispensables à la saisie guidée des adresses et à tout enlèvement hors de Belgique (plusieurs centaines de milliers d'entrées, et bien davantage avec les listes complètes du Royaume-Uni et des Pays-Bas, importées par défaut : comptez des dizaines de minutes, ou ajoutez `--sans-listes-completes` pour garder leur liste habituelle ; GeoNames ne publie pas la Grèce, dont les localités se vérifient alors en ligne) :

```bash
php artisan geo:import-postal-codes
```

### Lancement

Trois terminaux :

```bash
php artisan serve
```

```bash
npm run dev
```

```bash
mailpit --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
```

L'application répond sur `http://127.0.0.1:8000`, la boîte de réception de développement sur `http://localhost:8025`.

### Envoi réel des courriels (Brevo)

En développement, Mailpit intercepte tout. Pour que les courriels partent vraiment (confirmation d'adresse, mot de passe, activation, factures), l'application passe par le relais SMTP de Brevo, sans paquet à installer :

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=identifiant-smtp-brevo
MAIL_PASSWORD=cle-smtp-brevo
MAIL_FROM_ADDRESS="noreply@votre-domaine.be"
MAIL_FROM_NAME="NBLogiTrack"
MAIL_TIMEOUT=10
```

1. **Identifiant et clé** : dans Brevo, *Paramètres › SMTP & API › SMTP*. C'est une clé SMTP, pas une clé d'API ; elle ne va que dans le fichier `.env`, jamais dans le dépôt.
2. **Domaine d'envoi** : dans *Expéditeurs & domaines*, faites authentifier le domaine de `MAIL_FROM_ADDRESS` (entrées DNS DKIM et DMARC fournies par Brevo). Sans cela, les courriels arrivent en indésirables ou sont refusés.
3. **Vérification** : `php artisan optimize:clear`, puis « Mot de passe oublié » sur votre propre compte.

Les adresses inventées du jeu de démonstration (`contact.be`, `client*.be`, `nblogitrack-test.eu`…) ne reçoivent rien : leurs courriels sont retenus et notés au journal, pour que leurs retours en erreur ne fassent pas suspendre le compte Brevo. La liste se règle par `MAIL_DOMAINES_BLOQUES` ; vide, tout part. Le domaine `nblogitrack.be`, lui, est enregistré : une redirection « toutes adresses » (catch-all) envoie les courriels de `admin@`, `client@`, des chauffeurs, etc. vers une vraie boîte, si bien qu'aucun ne revient en erreur. L'offre gratuite de Brevo envoie 300 courriels par jour, assez pour une démonstration. Chaque expédition envoie au client jusqu'à quatre courriels (commande enregistrée, camion affecté, livraison effectuée, facture), et le 1ᵉʳ du mois part une facture par client qui a un reste à facturer : au-delà de 300 courriels par jour, passez à une offre payante.

### Paiement en ligne (Stripe)

Le client règle une facture, ou son solde après un paiement partiel, par Stripe Checkout : carte, Bancontact et les autres moyens activés dans le tableau de bord Stripe. La page de paiement s'affiche dans sa langue.

1. Renseignez dans `.env` les clés du compte Stripe (`sk_test_…` pour les essais, `sk_live_…` en production) :

   ```bash
   STRIPE_KEY=pk_test_...        # clé publique, pas encore lue : le paiement passe par la page Stripe Checkout
   STRIPE_SECRET=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

2. Dans le tableau de bord Stripe (Développeurs > Webhooks), déclarez l'adresse `https://votre-domaine/stripe/webhook` avec les événements `checkout.session.completed`, `checkout.session.async_payment_succeeded` et `checkout.session.async_payment_failed`, puis copiez son secret de signature dans `STRIPE_WEBHOOK_SECRET`. En local, `stripe listen --forward-to localhost:8000/stripe/webhook` affiche ce secret.

Le paiement s'enregistre dès le retour du client sur le site, et par la notification signée même s'il ferme son navigateur ; il n'est jamais compté deux fois. Deux onglets ou deux clics reprennent la même session de paiement, et un paiement différé (virement SEPA) en cours bloque un second paiement jusqu'à son arrivée ou son échec. Un paiement reçu malgré tout en trop est signalé sur la facture, à rembourser depuis Stripe. Sans clé, le bouton « Payer en ligne » est masqué et le virement avec communication structurée reste proposé. Carte d'essai : `4242 4242 4242 4242`, date future, n'importe quel code.

`STRIPE_API_BASE`, vide en production, permet de pointer vers un émulateur (stripe-mock) pour les tests de bout en bout.

### Tâches planifiées

Six traitements tournent d'eux-mêmes. En production, l'ordonnanceur doit être appelé chaque minute :

```bash
* * * * * cd /chemin/vers/nblogitrack && php artisan schedule:run >> /dev/null 2>&1
```

| Quand | Commande | Rôle |
|---|---|---|
| Le 1ᵉʳ du mois à 4 h | `factures:generer` | Facture ce qui reste du mois écoulé (suppléments posés après la livraison, indemnités d'annulation, livraison dont la facture a échoué) et envoie chaque facture par courriel |
| Chaque matin à 4 h 15 | `factures:envoyer-brouillons` | Envoie, le jour venu, les factures datées dans le futur restées en brouillon |
| Le 2 du mois à 4 h 45 | `pieces:purger` | Efface les factures, avoirs et paiements arrivés au terme de leurs sept ans de conservation (à compter du 1ᵉʳ janvier qui suit leur année), avec les ordres qu'ils justifient, leurs suppléments, leurs positions et leur devis ; un ordre annulé sans frais après trois ans ; une entreprise désinscrite quand il ne lui reste aucune pièce |
| Chaque nuit à 3 h 30 | `positions:purger` | Efface les positions de route des expéditions livrées ou annulées depuis plus de sept jours, et leurs jalons après un an |
| Chaque lundi à 3 h 45 | `journaux:purger` | Applique les durées de conservation : journal d'activité, appels d'API, clés révoquées et demandes d'accès traitées (douze mois), mesure d'audience (treize mois), inscriptions refusées (six mois), demandes de devis sans suite (deux ans, pièces jointes comprises) |
| Chaque nuit à 0 h 15 | `chauffeurs:cloturer-departs` | Ferme le compte d'un chauffeur le jour de son départ enregistré à l'avance ; un an après, efface ses données de gestion (permis, examens, cartes, coordonnées) |
| Chaque minute | `queue:work --stop-when-empty` | Vide la file d'attente (note aux conducteurs) ; un worker permanent sous Supervisor peut la remplacer |
| Chaque nuit | `queue:prune-failed`, `auth:clear-resets`, purge du cache périmé | Entretien |

Les six traitements signalent leur échec au journal d'activité (« Échec d'une tâche planifiée ») et ne sont pas relancés tant qu'ils tournent encore ; la file d'attente non plus. L'image Docker les relance aussi à chaque démarrage : sur l'offre gratuite, le service s'endort et l'ordonnanceur ne rattrape pas une tâche dont l'heure est passée pendant le sommeil. Ils ne font rien quand tout est à jour.

Tous restent lançables à la main. `factures:generer` accepte `--mois=AAAA-MM`, `--tout`, `--essai` et `--sans-envoi` ; la relancer ne refacture rien, puisqu'elle ignore les expéditions qui portent déjà une ligne de facture.

### Mise en production

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan traductions:synchroniser                     # textes de l'interface (retouches de l'écran Traductions conservées)
php artisan optimize                                     # configuration, routes, vues, événements en cache
```

- **Fichier `.env`** : `APP_ENV=production`, `APP_DEBUG=false` (jamais `true` : la page de débogage affiche la configuration et les requêtes), `APP_URL` exact en `https://` (seul ce domaine et ses sous-domaines sont servis), `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE` absent ou à `true`, `LOG_STACK=daily`, `LOG_LEVEL=warning`, relais SMTP (Brevo, voir plus haut) avec `MAIL_TIMEOUT=10`, compte PostgreSQL dédié.
- **Derrière un répartiteur de charge ou un CDN** : `TRUSTED_PROXIES` avec leurs adresses, sinon HTTPS n'est pas reconnu et tous les visiteurs partagent les mêmes limites d'essais. `*` seulement si le serveur n'est joignable **que** par ce répartiteur (pare-feu) : sinon n'importe qui choisit son adresse IP avec un en-tête `X-Forwarded-For` et contourne les limites.
- **PHP** : OPcache actif (`opcache.validate_timestamps=0`, puis `php artisan optimize` et rechargement de PHP-FPM à chaque déploiement) ; `upload_max_filesize=10M` et `post_max_size=55M` (déjà dans `public/.user.ini` pour PHP-FPM) ; `max_execution_time` de 30 s suffit.
- **Serveur web** : `public/.htaccess` compresse les réponses et met en cache un an les fichiers de `public/build`. Sous nginx, reprendre ces règles (`gzip on`, `expires 1y` sur `/build/assets/`).
- **Facture électronique** : `PEPPOL_URL` et `PEPPOL_CLE` du point d'accès du prestataire choisi (obligatoire en B2B belge depuis 2026).

### Déploiement en ligne (Render et Supabase)

L'application se déploie telle quelle avec le `Dockerfile` du dépôt : l'image compile l'interface, installe PHP 8.4 et ses extensions, puis, à chaque démarrage, joue les migrations, met la configuration en cache, rattrape les purges et la facturation manquées pendant la veille, lance l'ordonnanceur chaque minute et sert le site par Apache. `render.yaml` décrit le service.

1. **Base de données (Supabase)** : créez un projet dans la région Europe, puis relevez dans *Connect › Session pooler* l'hôte (`aws-0-eu-central-1.pooler.supabase.com`), l'utilisateur (`postgres.<référence du projet>`) et le mot de passe. Le pooler de session passe en IPv4, ce que Render exige.
2. **Clé de l'application** : `php artisan key:generate --show` sur votre poste ; gardez la valeur `base64:…`.
3. **Service (Render)** : *New › Blueprint*, choisissez ce dépôt : Render lit `render.yaml`. Saisissez les valeurs demandées : `APP_KEY`, `APP_URL` (l'adresse que Render attribue, en `https://`), `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, l'accès SMTP de Brevo, l'adresse d'expédition (`MAIL_FROM_ADDRESS`) et les clés Stripe de test. Le plan utilise le port SMTP 2525 : l'offre gratuite de Render bloque les ports 25, 465 et 587 en sortie, et Brevo accepte aussi le 2525.
4. **Premier déploiement** : Render construit l'image (quelques minutes) ; les migrations créent les tables et le dictionnaire des traductions.
5. **Jeu de démonstration** : le jeu de données refuse de s'exécuter en production. Lancez-le depuis votre poste vers Supabase, en surchargeant la connexion pour la seule session PowerShell :

   ```powershell
   $env:DB_HOST = "aws-0-eu-central-1.pooler.supabase.com"
   $env:DB_PORT = "5432"
   $env:DB_DATABASE = "postgres"
   $env:DB_USERNAME = "postgres.<référence du projet>"
   $env:DB_PASSWORD = Read-Host "Mot de passe Supabase"
   $env:DB_SSLMODE = "require"
   php artisan optimize:clear
   php artisan migrate:fresh --seed --force
   Remove-Item Env:DB_HOST, Env:DB_PORT, Env:DB_DATABASE, Env:DB_USERNAME, Env:DB_PASSWORD, Env:DB_SSLMODE
   ```

   Les variables disparaissent à la fermeture de PowerShell : votre base locale n'est pas touchée.

   Les codes postaux se réimportent seuls au démarrage suivant du serveur Render, en arrière-plan (quelques minutes ; un import coupé par la mise en veille reprend là où il s'était arrêté). Le Royaume-Uni et les Pays-Bas y gardent leur liste habituelle (SW1A, 1012) : leurs listes complètes dépasseraient la base gratuite de 500 Mo. Pour lancer l'import soi-même vers Supabase : `php artisan geo:import-postal-codes --si-absents --sans-listes-completes`.
6. **Stripe** : déclarez le webhook `https://<adresse Render>/stripe/webhook` (voir *Paiement en ligne*) et copiez son secret dans `STRIPE_WEBHOOK_SECRET`.

L'offre gratuite de Render met le service en veille après quinze minutes sans visite : le premier chargement prend alors une minute. L'offre Starter reste éveillée. Supabase gratuit met le projet en pause après une semaine sans activité : ouvrez le site avant une démonstration.

### Sauvegardes

Rien de ce qui suit n'est encore en place : la version de démonstration, sur les offres gratuites, n'a pas de sauvegarde automatique. À prévoir avec l'hébergement de production :

- **Base de données** : `pg_dump -Fc` chaque nuit, gardé 30 jours hors du serveur, et archivage continu des WAL si une perte de quelques heures n'est pas acceptable.
- **Fichiers** : `storage/app` (pièces jointes des devis, documents publiés), avec la même rétention.
- **Test de restauration** : restaurer la sauvegarde sur une base vide au moins une fois par trimestre ; une sauvegarde jamais restaurée n'est pas une sauvegarde.

### Comptes de démonstration

Le jeu de données ne s'exécute qu'en environnement `local` ou `testing` : lancé ailleurs, il refuse de vider les tables.

Chaque table métier y porte au moins cent lignes cohérentes entre elles : 270 comptes, 148 entreprises, 110 chauffeurs et 110 véhicules, 309 commandes, 120 demandes de devis (dont 30 transformées en commande), 165 suppléments, 158 factures et quelque 170 paiements (acomptes compris, quelques factures laissées en retard ; seules les factures échues sont réglées, le nombre suit donc la date du chargement), 271 congés et passages au garage, 434 positions de suivi, 400 appels d'API journalisés et près de 2 000 entrées au journal d'activité. Les tables de référence (grilles tarifaires, pages, registre des traitements, clés d'API) gardent leur taille naturelle, et les demandes d'accès à l'API partent vides.

| Rôle | Adresse | Mot de passe |
|---|---|---|
| Administrateur | `admin@nblogitrack.be` | `Nblogitrack2026@` |
| Planificateur | `planner@nblogitrack.be` | `Nblogitrack2026@` |
| Client — gestionnaire de l'entreprise (Demo Transport SA) | `client@nblogitrack.be` | `Nblogitrack2026@` |
| Client — commandes (même entreprise) | `commandes@nblogitrack.be` | `Nblogitrack2026@` |
| Client — comptabilité (même entreprise) | `comptabilite@nblogitrack.be` | `Nblogitrack2026@` |
| Chauffeur (une mission en cours) | `wim.peeters121@nblogitrack.be` | `password` |

Le mot de passe `Nblogitrack2026@` s'écrit avec un seul N majuscule et se termine par `@`. Deux cent trente-sept autres comptes du jeu de données utilisent `password` ; les vingt-sept comptes `…@nblogitrack-test.eu`, créés par le formulaire d'inscription, ont chacun un mot de passe différent. Ces identifiants sont publics et ne valent que pour une base de démonstration : en production, créez de vrais comptes et ne lancez pas le jeu de données.

---

## Qualité

La suite de tests tourne sur PostgreSQL, sur une base dédiée dont le nom se termine par `_test` : la suite recrée toutes les tables au démarrage (`migrate:fresh`), chaque test tourne ensuite dans une transaction annulée, et `Tests\TestCase` refuse de démarrer ailleurs.

```bash
php artisan test
```

```bash
vendor/bin/pint
```

Six cent quatre-vingt-treize tests couvrent l'authentification, le cloisonnement entre rôles, le calcul du prix au serveur et la cohérence des formules entre elles, l'interface de programmation, la facturation (acheteur figé, TVA, avoirs, paiements partiels, suppléments) et son envoi par courriel, le paiement en ligne, le cycle de vie d'une mission de l'affectation à la livraison (transitions atomiques, réaffectation en route, heure, réceptionnaire et réserves de la livraison), le contrôle de chaque affectation (permis, ADR, groupage, chevauchements, indisponibilités, recontrôle à la prise en charge), les enlèvements hors de Belgique (trajets, calendriers, premier enlèvement à l'heure, fuseaux) et le fret retour (tarif, plancher national, fenêtre, capacité, planification), la demande de devis complète (champs conditionnels, pièces jointes, registres de TVA, transformation en commande), les livraisons vers la Grèce, l'annulation par le client, les incidents en mission (immobilisation, reprise, décision du planificateur, alertes au personnel), les avis envoyés au client, la facture émise à la livraison (chauffeur ou planificateur, suppléments repris, jamais deux fois, livraison maintenue si la facture échoue), la facturation immédiate, l'encours et le plafond de crédit, l'interface de programmation et sa documentation (demande d'accès, journal des refus de limite), les durées de conservation légales (pièces, jalons, chauffeurs partis, clés révoquées), la traduction complète de l'application, la politique de sécurité du contenu, l'acceptation des conditions à l'inscription, la désinscription par suppression logique et chacun des constats de l'audit de sécurité. Le style du code PHP suit la convention Laravel, vérifiée par Pint.

L'intégration continue exécute les deux à chaque proposition de fusion et à chaque envoi sur `main`, avec un service PostgreSQL 16 et la compilation du front.

---

## État du projet

Version **beta** en cours de développement. Chaque version est publiée comme *release* du dépôt (v0.1.0-alpha, v0.2.0-beta, v0.3.0-beta), et le travail passe par des propositions de fusion (*pull requests*).

---

## Licence

© 2026 Soufiane Achraa. Tous droits réservés.
