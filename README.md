# NBLogiTrack — Gestion de transport (TMS)

> Application web de gestion de transport développée dans le cadre de mon épreuve intégrée à TECHGEST ICCBXL.

**Auteur :** Soufiane Achraa — Épreuve intégrée 2025-2026 — TECHGEST ICCBXL  
**Version :** beta (en développement)  
**Stack :** Laravel · React · Inertia · Vite · Tailwind CSS · PostgreSQL

---

## Objectif

NBLogiTrack suit une expédition de bout en bout, de la commande du client jusqu'au paiement de la facture :

1. L'**entreprise** s'inscrit ; son identité est vérifiée auprès des registres officiels européens
2. Un **administrateur** valide la demande ; l'entreprise reçoit son e-mail d'activation
3. Le **client** passe une commande de transport et obtient une estimation de prix en temps réel
4. Le **planificateur** affecte la commande à un véhicule et à un chauffeur, puis organise les tournées
5. Le **chauffeur** consulte ses missions et confirme la livraison
6. La **facture** est générée, transmise au format électronique, puis réglée en ligne

**Règle métier — Suivi public :** chaque expédition reçoit un numéro de suivi unique, consultable sans compte depuis une page publique, à l'aide d'un code d'accès transmis au client.

**Règle métier — Validation obligatoire :** une entreprise inscrite ne peut pas se connecter tant qu'un administrateur ne l'a pas validée. Une société en faillite, en liquidation ou en réorganisation judiciaire est refusée automatiquement.

---

## Rôles

| Rôle | Accès |
|---|---|
| **Client** | Passe des commandes, suit ses expéditions, règle ses factures |
| **Chauffeur** | Consulte ses missions, met à jour les statuts, confirme les livraisons |
| **Planificateur** | Affecte les véhicules et les chauffeurs, organise les tournées |
| **Administrateur** | Valide les entreprises clientes, consulte le journal d'activité, gère la flotte et les utilisateurs |
| **Visiteur** | Suit une expédition via son numéro et son code, sans authentification |

---

## Fonctionnalités

| Domaine | Description | État |
|---|---|---|
| **Comptes & rôles** | Inscription, connexion, autorisations par rôle (Breeze + Gate) | ✅ alpha |
| **Vérification des entreprises** | Contrôle du numéro de TVA auprès de VIES, lecture des registres belge et français, identifiant Peppol des 27 pays | ✅ alpha |
| **Validation des inscriptions** | Examen par l'administrateur, e-mails d'activation et de refus motivé | ✅ alpha |
| **Création de commande** | Saisie guidée de l'adresse, distance routière réelle, estimation du prix en temps réel | ✅ alpha |
| **Catalogue des ordres** | Liste, recherche par colonne, filtrage selon le rôle, fiche détaillée d'une expédition | ✅ alpha |
| **Planification** | Affectation véhicule et chauffeur, contrôle de capacité et de certification ADR, transitions de statut | ✅ alpha |
| **Suivi public** | Consultation d'un envoi (numéro + code), état de livraison | ✅ alpha |
| **Journal d'activité** | Date, utilisateur, type d'action et adresse IP, avec filtres | ✅ alpha |
| **Tableau de bord** | Indicateurs clés (KPI) + derniers ordres | ✅ alpha |
| **Gestion de la flotte** | Véhicules et chauffeurs, contrôle technique, permis et statut d'emploi | ✅ alpha |
| **Facturation** | Une facture par client et par mois, autoliquidation intracommunautaire, communication structurée belge, PDF et format Peppol (EN 16931) | ✅ alpha |
| **Paiement** | Règlement d'une facture en ligne (Stripe), notification signée vérifiée au centime | ✅ alpha |
| **Multilingue** | Français, néerlandais et anglais, avec écran d'administration des traductions | ✅ alpha |
| **Achats et TVA** | Factures de carburant et de péage, synthèse de TVA mensuelle | ✅ alpha |
| **Devis** | Demande de devis publique, traitement par le personnel | ✅ alpha |
| **Suivi géolocalisé** | Jalons horodatés et position en direct, activables par mission, purgés à sept jours | ✅ alpha |
| **API REST** | Interface versionnée pour les partenaires, clés révocables, limitation de débit | ✅ alpha |
| **Pages publiques** | Mentions légales, confidentialité et conditions générales, modifiables sans redéploiement | ✅ alpha |
| **Conformité RGPD** | Registre des traitements, durées de conservation appliquées par tâches planifiées | ✅ alpha |
| **Preuve de livraison** | Signature du destinataire depuis l'espace chauffeur | 🔜 beta |

---

## Sources de données publiques

L'application interroge plusieurs services ouverts, sans clé d'accès :

| Service | Usage |
|---|---|
| **VIES** (Commission européenne) | Validation du numéro de TVA, raison sociale et adresse du siège |
| **Banque-Carrefour des Entreprises** (Belgique) | Dirigeant, secteur d'activité, situation juridique |
| **Recherche d'entreprises** (France) | Dirigeant, code NACE, état administratif |
| **GeoNames** | Villes et codes postaux des 27 États membres |
| **Photon** et **Overpass** (OpenStreetMap) | Rues et numéros de police existants |
| **OSRM** | Distance routière entre deux adresses |

---

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end | Laravel 12 (PHP 8.2+) |
| Front-end | React 19 + Inertia + Vite |
| Mise en forme | Tailwind CSS |
| Base de données | PostgreSQL 16 |
| Authentification | Laravel Breeze (session) |
| Messagerie (développement) | Mailpit |
| Paiement | Stripe |
| Facturation électronique | Peppol — norme EN 16931 |
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

Importez enfin les codes postaux européens, indispensables à la saisie guidée des adresses (environ 610 000 entrées, quelques minutes) :

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

### Tâches planifiées

Trois traitements tournent d'eux-mêmes. En production, l'ordonnanceur doit être appelé chaque minute :

```bash
* * * * * cd /chemin/vers/nblogitrack && php artisan schedule:run >> /dev/null 2>&1
```

| Quand | Commande | Rôle |
|---|---|---|
| Le 1ᵉʳ du mois à 4 h | `factures:generer` | Facture les transports livrés du mois écoulé |
| Chaque nuit à 3 h 30 | `positions:purger` | Efface les positions de route au-delà de sept jours |
| Chaque lundi à 3 h 45 | `journaux:purger` | Applique les douze mois de conservation du journal |

Les trois restent lançables à la main. `factures:generer` accepte `--mois=AAAA-MM`, `--tout` et `--essai` ; la relancer ne refacture rien, puisqu'elle ignore les expéditions qui portent déjà une ligne de facture.

### Comptes de démonstration

Le jeu de données ne s'exécute qu'en environnement `local` ou `testing` : lancé ailleurs, il refuse de vider les tables.

| Rôle | Adresse | Mot de passe |
|---|---|---|
| Administrateur | `admin@nblogitrack.be` | `Nblogitrack2026@` |
| Planificateur | `planner@nblogitrack.be` | `Nblogitrack2026@` |
| Client | `client@nblogitrack.be` | `Nblogitrack2026@` |
| Chauffeur | `wim.peeters121@nblogitrack.be` | `password` |

Les deux cent soixante autres comptes du jeu de données utilisent `password`. Ces identifiants sont publics et ne valent que pour une base de démonstration.

---

## Qualité

La suite de tests tourne sur PostgreSQL, sur une base dédiée dont le nom se termine par `_test` : chaque classe vide la base avant de commencer, et `Tests\TestCase` refuse de démarrer ailleurs.

```bash
php artisan test
```

```bash
vendor/bin/pint
```

Quatre-vingt-sept tests couvrent l'authentification, le cloisonnement entre rôles, le calcul du prix au serveur, l'interface de programmation et la facturation. Le style du code PHP suit la convention Laravel, vérifiée par Pint.

L'intégration continue exécute les deux à chaque proposition de fusion, avec un service PostgreSQL 16 et la compilation du front.

---

## État du projet

Version **alpha** en cours de développement. Le suivi des tâches et des versions se fait via les *issues* et les *milestones* du dépôt.

---

## Licence

© 2026 Soufiane Achraa. Tous droits réservés.
