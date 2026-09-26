# NBLogiTrack — Gestion de transport (Transport Management System)

> Application web de gestion de transport développée dans le cadre de mon épreuve intégrée à TECHGEST, Institut des Carrières Commerciales de Bruxelles.

**Auteur :** Soufiane Achraa — Épreuve intégrée 2025-2026 — TECHGEST, Institut des Carrières Commerciales de Bruxelles  
**Version :** beta (en développement)  
**Stack :** Laravel · React · Inertia · Vite · Tailwind CSS · PostgreSQL

---

## Objectif

NBLogiTrack suit une expédition de bout en bout, de la commande du client jusqu'au paiement de la facture :

1. L'**entreprise** s'inscrit ; son identité est vérifiée auprès des registres officiels européens
2. Un **administrateur** valide la demande ; l'entreprise reçoit son e-mail d'activation
3. Le **client** passe une commande de transport et obtient une estimation de prix en temps réel
4. Le **planificateur** affecte chaque commande à un véhicule et à un chauffeur ; l'application contrôle, sur toute la durée de la mission, le permis qu'exige le véhicule, les documents du chauffeur, la charge, l'équipement et la certification pour matières dangereuses (ADR), les congés et les temps de conduite
5. Le **chauffeur** consulte ses missions et confirme la livraison
6. La **facture** est générée en PDF et au format électronique européen, envoyée par courriel, puis réglée en ligne

**Règle métier — Suivi public :** chaque expédition reçoit un numéro de suivi unique, consultable sans compte depuis une page publique, à l'aide d'un code d'accès transmis au client.

**Règle métier — Validation obligatoire :** une entreprise inscrite ne peut pas se connecter tant qu'un administrateur ne l'a pas validée. Une société en faillite, en liquidation ou en réorganisation judiciaire est refusée automatiquement.

---

## Rôles

| Rôle | Accès |
|---|---|
| **Client** | Une entreprise, plusieurs comptes : l'administrateur de l'entreprise invite ses collègues et choisit leur rôle (administrateur, commandes, comptabilité). Les commandes passent, suivent et annulent les expéditions ; la comptabilité consulte et règle les factures |
| **Chauffeur** | Consulte ses missions, confirme l'enlèvement puis la livraison |
| **Planificateur** | Affecte un véhicule et un chauffeur à chaque commande, réaffecte en cas d'imprévu |
| **Administrateur** | Valide les entreprises clientes, consulte le journal d'activité, gère la flotte et les utilisateurs |
| **Visiteur** | Suit une expédition via son numéro et son code, sans authentification |

---

## Fonctionnalités

| Domaine | Description | État |
|---|---|---|
| **Comptes & rôles** | Inscription, connexion, autorisations par rôle (Breeze + Gate) | ✅ alpha |
| **Vérification des entreprises** | Contrôle du numéro de taxe sur la valeur ajoutée (TVA) auprès du service européen VIES, lecture des registres belge et français, identifiant sur le réseau Peppol des 27 pays | ✅ alpha |
| **Validation des inscriptions** | Examen par l'administrateur, e-mails d'activation et de refus motivé | ✅ alpha |
| **Création de commande** | Saisie guidée de l'adresse en entonnoir (pays, ville, code postal, rue, numéro : chaque niveau limite le suivant ; un numéro que la cartographie ne connaît pas est accepté et localisé à la rue), distance routière réelle, estimation du prix en temps réel calculée par le serveur : Éco ≤ Standard ≤ Express, et le groupage ne coûte jamais plus qu'un camion dédié. Pour les marchandises souvent soumises à l'ADR, le client déclare explicitement si son envoi l'est ; une commande plus lourde que le plus gros camion de la flotte est refusée ; le volume se saisit. Les livraisons vers la Grèce, dont les codes postaux ne sont pas publiés, sont vérifiées en ligne | ✅ alpha |
| **Catalogue des ordres** | Liste, recherche par colonne, filtrage selon le rôle, fiche détaillée d'une expédition | ✅ alpha |
| **Annulation par le client** | Gratuite avant l'affectation d'un camion, indemnité de 25 % du prix (50 € minimum) ensuite, portée sur la facture du mois ; impossible en ligne une fois la marchandise chargée (article 8 bis des conditions générales) | ✅ beta |
| **Planification** | Affectation véhicule et chauffeur contrôlée par un service unique, sur toute la durée de la mission : permis exigé par le véhicule (un tracteur de 44 t exige le CE, quelle que soit sa carrosserie), documents du chauffeur (permis, visite médicale, code 95, carte tachygraphe), véhicule équipé et chauffeur certifié ADR jusqu'au dernier jour, charge et volume cumulés en groupage, contrôle technique, congés et immobilisations datés, chevauchements, temps de conduite (9 h par jour, 56 h par semaine, repos du septième jour). L'écran grise ce que le serveur refuserait, à la date de la mission ; une mission devenue non conforme (document expiré, fiche corrigée) est signalée, et le chauffeur ne peut plus la prendre en charge. Transitions de statut (en attente, affecté, en cours, livré, annulé) centralisées et atomiques, affectation sous verrou : deux planificateurs ne réservent pas le même camion ; une marchandise chargée ne revient jamais en attente, elle se réaffecte à un autre camion ; la livraison garde l'heure, le réceptionnaire et les réserves | ✅ alpha |
| **Suivi public** | Consultation d'un envoi (numéro + code), état de livraison | ✅ alpha |
| **Journal d'activité** | Date, utilisateur, type d'action et adresse IP, avec filtres | ✅ alpha |
| **Tableau de bord** | Indicateurs clés et derniers ordres | ✅ alpha |
| **Gestion de la flotte** | Véhicules et chauffeurs, contrôle technique, permis exigé par chaque véhicule et équipement ADR, permis, code 95, carte tachygraphe et certificat ADR des chauffeurs, statut d'emploi, départs programmés, congés et passages au garage datés à l'avance | ✅ alpha |
| **Facturation** | Une facture par client et par mois : transports, indemnités d'annulation et suppléments posés par le planificateur (attente, manutention). Identité de l'acheteur figée à l'émission, catégorie de TVA par ligne (21 %, autoliquidation intracommunautaire, hors champ hors Union), avoirs numérotés à part qui annulent une facture, avec ou sans refacturation immédiate, communication structurée belge, PDF et fichier UBL au format Peppol BIS 3.0 (norme européenne EN 16931), envoi par courriel avec les deux fichiers en pièces jointes. La transmission sur le réseau Peppol demande un point d'accès certifié, pas encore raccordé | ✅ beta |
| **Paiement** | Paiements enregistrés un à un (virement, espèces, en ligne), paiements partiels et reste dû ; règlement en ligne du solde (Stripe), enregistré dès le retour du client ou par la notification signée, jamais deux fois, paiement différé (SEPA) suivi, trop-perçu signalé ; rapport de TVA qui déduit les avoirs | ✅ alpha |
| **Multilingue** | Français, néerlandais et anglais partout : écrans, messages, courriels et facture PDF, dans la langue de chaque utilisateur ; écran d'administration des traductions, et un test qui refuse toute clé sans ses trois traductions | ✅ beta |
| **Achats et TVA** | Factures de carburant et de péage, synthèse de TVA mensuelle | ✅ beta |
| **Devis** | Demande de devis publique, traitement par le personnel | ✅ beta |
| **Suivi géolocalisé** | Jalons horodatés et position en direct, activables par mission, purgés à sept jours ; carte vectorielle aux couleurs de Waze (itinéraire routier, péages, camion en route), avec repli sur la carte OpenStreetMap standard | ✅ beta |
| **Interface de programmation (API REST)** | Interface versionnée pour les partenaires, clés révocables, limitation de débit | ✅ beta |
| **Pages publiques** | Mentions légales, confidentialité et conditions générales, modifiables sans redéploiement | ✅ beta |
| **Conformité RGPD** | Registre des traitements et durées de conservation du règlement général sur la protection des données, appliqués par tâches planifiées | ✅ beta |
| **Tests et intégration continue** | 301 tests sur PostgreSQL, exécutés à chaque proposition de fusion | ✅ beta |
| **Preuve de livraison** | Signature du destinataire depuis l'espace chauffeur | 🔜 à venir |

---

## Sources de données publiques

L'application interroge plusieurs services ouverts, sans clé d'accès :

| Service | Usage |
|---|---|
| **VIES**, système d'échange d'informations sur la TVA (Commission européenne) | Validation du numéro de TVA, raison sociale et adresse du siège |
| **Banque-Carrefour des Entreprises** (Belgique) | Dirigeant, secteur d'activité, situation juridique |
| **Recherche d'entreprises** (France) | Dirigeant, code d'activité NACE, état administratif |
| **GeoNames** | Villes et codes postaux des pays desservis (la Grèce, que GeoNames ne publie pas, est vérifiée par Photon) |
| **Photon** et **Overpass** (OpenStreetMap) | Villes, rues et numéros de police existants |
| **Base Adresse Nationale** (France) et **PDOK** (Pays-Bas) | Rues françaises et néerlandaises |
| **OSRM** (Open Source Routing Machine) | Distance routière et itinéraire entre deux adresses |
| **OpenFreeMap** | Fond de carte vectoriel (tuiles OpenMapTiles), sans clé ni quota |
| **OpenStreetMap** | Carte de repli, pour les navigateurs sans WebGL 2 |

---

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end | Laravel 12 (PHP 8.2+) |
| Front-end | React 18 + Inertia + Vite |
| Mise en forme | Tailwind CSS |
| Cartographie | Leaflet et MapLibre GL (fond vectoriel) |
| Base de données | PostgreSQL 16 |
| Authentification | Laravel Breeze (session) |
| Messagerie (développement) | Mailpit |
| Paiement | Stripe |
| Facturation électronique | UBL Peppol BIS 3.0 — norme européenne EN 16931 (fichier généré ; transmission par point d'accès à raccorder) |
| Tests | PHPUnit sur PostgreSQL |
| Intégration continue | GitHub Actions — style, tests et compilation |

---

## Installation

### Prérequis

PHP 8.2+, Composer, Node.js 22+ et PostgreSQL 16.

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

Une base créée avant le contrôle des affectations garde ses données, mais les règles s'appliquent aussitôt : renseignez la date de fin du certificat ADR des chauffeurs certifiés (sans elle, aucune marchandise dangereuse ne leur est confiée) et cochez « Équipé ADR » sur les véhicules concernés (les citernes le sont d'office). `php artisan migrate:fresh --seed` repart d'un jeu de démonstration cohérent, en effaçant les données.

Importez enfin les codes postaux européens, indispensables à la saisie guidée des adresses (environ 610 000 entrées, quelques minutes ; GeoNames ne publie pas la Grèce, dont les localités se vérifient alors en ligne) :

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

### Paiement en ligne (Stripe)

Le client règle une facture, ou son solde après un paiement partiel, par Stripe Checkout : carte, Bancontact et les autres moyens activés dans le tableau de bord Stripe. La page de paiement s'affiche dans sa langue.

1. Renseignez dans `.env` les clés du compte Stripe (`sk_test_…` pour les essais, `sk_live_…` en production) :

   ```bash
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

2. Dans le tableau de bord Stripe (Développeurs > Webhooks), déclarez l'adresse `https://votre-domaine/stripe/webhook` avec les événements `checkout.session.completed`, `checkout.session.async_payment_succeeded` et `checkout.session.async_payment_failed`, puis copiez son secret de signature dans `STRIPE_WEBHOOK_SECRET`. En local, `stripe listen --forward-to localhost:8000/stripe/webhook` affiche ce secret.

Le paiement s'enregistre dès le retour du client sur le site, et par la notification signée même s'il ferme son navigateur ; il n'est jamais compté deux fois. Deux onglets ou deux clics reprennent la même session de paiement, et un paiement différé (virement SEPA) en cours bloque un second paiement jusqu'à son arrivée ou son échec. Un paiement reçu malgré tout en trop est signalé sur la facture, à rembourser depuis Stripe. Sans clé, le bouton « Payer en ligne » est masqué et le virement avec communication structurée reste proposé. Carte d'essai : `4242 4242 4242 4242`, date future, n'importe quel code.

`STRIPE_API_BASE`, vide en production, permet de pointer vers un émulateur (stripe-mock) pour les tests de bout en bout.

### Tâches planifiées

Quatre traitements tournent d'eux-mêmes. En production, l'ordonnanceur doit être appelé chaque minute :

```bash
* * * * * cd /chemin/vers/nblogitrack && php artisan schedule:run >> /dev/null 2>&1
```

| Quand | Commande | Rôle |
|---|---|---|
| Le 1ᵉʳ du mois à 4 h | `factures:generer` | Facture les transports livrés du mois écoulé et envoie chaque facture par courriel |
| Chaque nuit à 3 h 30 | `positions:purger` | Efface les positions de route au-delà de sept jours |
| Chaque lundi à 3 h 45 | `journaux:purger` | Applique les douze mois de conservation du journal |
| Chaque nuit à 0 h 15 | `chauffeurs:cloturer-departs` | Ferme le compte d'un chauffeur le jour de son départ enregistré à l'avance |

Tous restent lançables à la main. `factures:generer` accepte `--mois=AAAA-MM`, `--tout`, `--essai` et `--sans-envoi` ; la relancer ne refacture rien, puisqu'elle ignore les expéditions qui portent déjà une ligne de facture.

### Comptes de démonstration

Le jeu de données ne s'exécute qu'en environnement `local` ou `testing` : lancé ailleurs, il refuse de vider les tables.

| Rôle | Adresse | Mot de passe |
|---|---|---|
| Administrateur | `admin@nblogitrack.be` | `Nblogitrack2026@` |
| Planificateur | `planner@nblogitrack.be` | `Nblogitrack2026@` |
| Client — administrateur de l'entreprise (Demo Transport SA) | `client@nblogitrack.be` | `Nblogitrack2026@` |
| Client — commandes (même entreprise) | `commandes@nblogitrack.be` | `Nblogitrack2026@` |
| Client — comptabilité (même entreprise) | `comptabilite@nblogitrack.be` | `Nblogitrack2026@` |
| Chauffeur (une mission en cours) | `wim.peeters121@nblogitrack.be` | `password` |

Le mot de passe `Nblogitrack2026@` s'écrit avec un seul N majuscule et se termine par `@`. Les deux cent soixante-quatre autres comptes du jeu de données utilisent `password`. Ces identifiants sont publics et ne valent que pour une base de démonstration : en production, créez de vrais comptes et ne lancez pas le jeu de données.

---

## Qualité

La suite de tests tourne sur PostgreSQL, sur une base dédiée dont le nom se termine par `_test` : chaque classe vide la base avant de commencer, et `Tests\TestCase` refuse de démarrer ailleurs.

```bash
php artisan test
```

```bash
vendor/bin/pint
```

Trois cent un tests couvrent l'authentification, le cloisonnement entre rôles, le calcul du prix au serveur et la cohérence des formules entre elles, l'interface de programmation, la facturation (acheteur figé, TVA, avoirs, paiements partiels, suppléments) et son envoi par courriel, le paiement en ligne, le cycle de vie d'une mission de l'affectation à la livraison (transitions atomiques, réaffectation en route, preuve de livraison), le contrôle de chaque affectation (permis, ADR, groupage, chevauchements, indisponibilités, recontrôle à la prise en charge), les livraisons vers la Grèce, l'annulation par le client, la traduction complète de l'application, la politique de sécurité du contenu, l'acceptation des conditions à l'inscription et chacun des constats de l'audit de sécurité. Le style du code PHP suit la convention Laravel, vérifiée par Pint.

L'intégration continue exécute les deux à chaque proposition de fusion, avec un service PostgreSQL 16 et la compilation du front.

---

## État du projet

Version **beta** en cours de développement. Le suivi des tâches et des versions se fait via les *issues* et les *milestones* du dépôt.

---

## Licence

© 2026 Soufiane Achraa. Tous droits réservés.
