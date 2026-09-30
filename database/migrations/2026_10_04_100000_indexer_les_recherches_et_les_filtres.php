<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Index des colonnes filtrees a chaque ecran (statut, client, dates,
 * camion, chauffeur), des cles etrangeres, et recherche « contient » par
 * trigrammes : sur 200 000 ordres, une recherche passait de 435 ms en
 * lecture complete de la table a quelques millisecondes.
 */
return new class extends Migration
{
    /** @var array<string, string> nom => definition */
    private const INDEX = [
        'to_client_statut' => 'transport_orders (client_id, status)',
        'to_statut_enlevement' => 'transport_orders (status, pickup_date)',
        'to_camion_statut' => 'transport_orders (vehicle_registration, status) WHERE vehicle_registration IS NOT NULL',
        'to_chauffeur_statut' => 'transport_orders (driver_id, status) WHERE driver_id IS NOT NULL',
        'to_porteuse' => 'transport_orders (backhaul_order_id) WHERE backhaul_order_id IS NOT NULL',
        'to_livraison_attendue' => "transport_orders (requested_delivery_date) WHERE status IN ('PENDING', 'ASSIGNED', 'IN_PROGRESS')",
        'to_creation' => 'transport_orders (created_date)',
        'to_grille' => 'transport_orders (tariff_grid_id)',
        'to_annule_par' => 'transport_orders (cancelled_by) WHERE cancelled_by IS NOT NULL',
        'qr_statut_creation' => 'quote_requests (status, created_at DESC)',
        'qr_commande' => 'quote_requests (converted_order_id) WHERE converted_order_id IS NOT NULL',
        'qr_traite_par' => 'quote_requests (handled_by) WHERE handled_by IS NOT NULL',
        'pv_jour_vues' => 'page_views (jour) INCLUDE (entree, appareil) WHERE evenement IS NULL',
        'users_client' => 'users (client_id) WHERE client_id IS NOT NULL',
        'contacts_client' => 'client_contacts (client_id)',
        'frais_ordre' => 'order_charges (transport_order_id)',
        'appels_cle' => 'api_requests (api_key_id)',
        'clients_valide_par' => 'clients (validated_by) WHERE validated_by IS NOT NULL',
        'clients_a_valider' => 'clients (company_name) WHERE NOT is_validated AND rejection_reason IS NULL',
    ];

    /** Colonnes cherchees avec « contient » (whereContient). */
    private const TRIGRAMMES = [
        'transport_orders' => ['tracking_number', 'pickup_address', 'delivery_address'],
        'clients' => ['company_name', 'vat_number'],
        'quote_requests' => ['reference', 'company_name', 'contact_name', 'email', 'vat_number'],
    ];

    public function up(): void
    {
        foreach (self::INDEX as $nom => $definition) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$nom} ON {$definition}");
        }

        // L'index evenement seul ne servait pas : presque toutes les lignes
        // ont un evenement nul.
        DB::statement('DROP INDEX IF EXISTS page_views_evenement_index');

        // unaccent() n'est pas « immuable » pour PostgreSQL et ne peut pas
        // entrer dans un index : f_unaccent l'enveloppe avec son dictionnaire.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
            AS $$ SELECT public.unaccent('public.unaccent'::regdictionary, $1) $$
        SQL);

        foreach (self::TRIGRAMMES as $table => $colonnes) {
            foreach ($colonnes as $colonne) {
                DB::statement("CREATE INDEX IF NOT EXISTS trgm_{$table}_{$colonne} ON {$table} USING gin (f_unaccent(({$colonne})::text) gin_trgm_ops)");
            }
        }
    }

    public function down(): void
    {
        foreach (self::TRIGRAMMES as $table => $colonnes) {
            foreach ($colonnes as $colonne) {
                DB::statement("DROP INDEX IF EXISTS trgm_{$table}_{$colonne}");
            }
        }

        foreach (array_keys(self::INDEX) as $nom) {
            DB::statement("DROP INDEX IF EXISTS {$nom}");
        }

        DB::statement('CREATE INDEX IF NOT EXISTS page_views_evenement_index ON page_views (evenement)');
    }
};
