# NBLogiTrack — Gestion de transport (TMS)

> Application web de gestion de transport développée dans le cadre de mon épreuve intégrée à TECHGEST ICCBXL.

**Auteur :** Soufiane Achraa — Épreuve intégrée 2025-2026 — TECHGEST ICCBXL  
**Version :** alpha (en développement)  
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
| **Gestion de la flotte** | Interface véhicules et chauffeurs | 🔜 beta |
| **Preuve de livraison** | Confirmation par le chauffeur depuis son espace | 🔜 beta |
| **Facturation** | Factures + format électronique Peppol (EN 16931) | 🔜 beta |
| **Paiement** | Règlement d'une facture en ligne (Stripe) | 🔜 beta |
| **Multilingue** | Interface et gestion des traductions | 🔜 beta |

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

---

## Installation

### Prérequis

PHP 8.2+, Composer, Node.js 18+ et PostgreSQL 16.

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

### Comptes de démonstration

Le jeu de données crée des comptes de test dont le mot de passe commun est `password`. Il ne s'exécute qu'en environnement `local` ou `testing` : lancé ailleurs, il refuse de vider les tables.

| Rôle | Adresse |
|---|---|
| Administrateur | `admin@nblogitrack.be` |
| Planificateur | `planner@nblogitrack.be` |
| Client | `client@nblogitrack.be` |

---

## Qualité

```bash
vendor/bin/pint
```

Le style du code PHP suit la convention Laravel, vérifiée par Pint.

---

## État du projet

Version **alpha** en cours de développement. Le suivi des tâches et des versions se fait via les *issues* et les *milestones* du dépôt.

---

## Licence

© 2026 Soufiane Achraa. Tous droits réservés.
