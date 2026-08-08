# NBLogiTrack — Gestion de transport (TMS)

> Application web de gestion de transport développée dans le cadre de mon épreuve intégrée à TECHGEST ICCBXL.

**Auteur :** Soufiane Achraa — Épreuve intégrée 2025-2026 — TECHGEST ICCBXL  
**Version :** alpha (en développement)  
**Stack :** Laravel · React · Vite · PostgreSQL

---

## Objectif

NBLogiTrack suit une expédition de bout en bout, de la commande du client jusqu'au paiement de la facture :

1. Le **client** passe une commande de transport et reçoit une estimation de prix
2. Le **planificateur** affecte la commande à un véhicule et à un chauffeur, puis organise les tournées
3. Le **chauffeur** consulte ses missions et confirme la livraison
4. La **facture** est générée, transmise au format électronique, puis réglée en ligne
5. Un **numéro de suivi** permet de pister l'expédition à tout moment

**Règle métier — Suivi public :** chaque expédition reçoit un numéro de suivi unique, consultable sans compte depuis une page publique.

---

## Rôles

| Rôle | Accès |
|---|---|
| **Client** | Passe des commandes, suit ses expéditions, règle ses factures |
| **Chauffeur** | Consulte ses missions, met à jour les statuts, confirme les livraisons |
| **Planificateur** | Affecte les véhicules et les chauffeurs, organise les tournées |
| **Administrateur** | Valide les entreprises clientes, gère la flotte et les utilisateurs |
| **Visiteur** | Suit une expédition via son numéro, sans authentification |

---

## Fonctionnalités

| Domaine | Description | État |
|---|---|---|
| **Comptes & rôles** | Inscription, connexion, autorisations par rôle (Breeze + Gate) | ✅ alpha |
| **Catalogue des ordres** | Liste des ordres de transport, recherche par colonne, filtrage selon le rôle | ✅ alpha |
| **Suivi public** | Consultation sécurisée d'un envoi (numéro + code), timeline d'état | ✅ alpha |
| **Tableau de bord** | Indicateurs clés (KPI) + derniers ordres | ✅ alpha |
| **Clients & flotte** | Données clients, véhicules, chauffeurs (interface de gestion à venir) | 🔜 beta |
| **Création de commande** | Passer un ordre de transport + estimation du prix | 🔜 beta |
| **Tournées & missions** | Affectation véhicule/chauffeur, statuts, preuve de livraison | 🔜 beta |
| **Facturation** | Factures + format électronique Peppol (EN 16931) | 🔜 beta |
| **Paiement** | Règlement d'une facture en ligne (Stripe) | 🔜 beta |

---

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end | Laravel (PHP 8.2+) |
| Front-end | React + Vite |
| Base de données | PostgreSQL |
| Authentification | Laravel Breeze (session) |
| Paiement | Stripe |
| Facturation électronique | Peppol — norme EN 16931 |

---

## Installation

### Prérequis

PHP 8.2+, Composer, Node.js 18+ et PostgreSQL.

### Étapes

```bash
git clone https://github.com/soufianeach-DEV/nblogitrack.git
cd nblogitrack

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Renseignez l'accès à la base PostgreSQL dans le fichier `.env`, puis créez les tables et lancez les serveurs :

```bash
php artisan migrate --seed

# back-end — http://localhost:8000
php artisan serve

# front-end Vite — nouveau terminal
npm run dev
```

---

## État du projet

Version **alpha** en cours de développement. Le suivi des tâches et des versions se fait via les *issues* et les *milestones* du dépôt.

---

## Licence

© 2026 Soufiane Achraa. Tous droits réservés.
