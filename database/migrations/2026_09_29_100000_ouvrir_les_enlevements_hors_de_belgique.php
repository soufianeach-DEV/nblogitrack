<?php

use App\Support\Adresse;
use App\Support\Pays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enlevements hors de Belgique et fret retour. Un import X -> BE est un
 * transport bilateral : ni cabotage (reglement 1072/2009, art. 8) ni
 * detachement (directive 2020/1057). Les trajets entre deux pays
 * etrangers restent sur devis : une contrainte le garantit en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->char('pickup_country', 2)->default('BE')->after('pickup_address');
            $table->char('delivery_country', 2)->default('BE')->after('delivery_address');
            $table->string('pricing_basis', 10)->default('LANE')->after('estimated_cost');
            $table->foreignId('backhaul_order_id')->nullable()->after('pricing_basis')
                ->constrained('transport_orders')->nullOnDelete();
            // Kilometres a vide jusqu'au lieu de chargement : ils comptent
            // dans le temps de conduite du chauffeur.
            $table->unsignedInteger('approche_km')->nullable()->after('distance_km');
            $table->string('shipper_name', 150)->nullable();
            $table->string('shipper_phone', 30)->nullable();
            $table->string('loading_reference', 60)->nullable();
        });

        // Tous les enlevements etaient en Belgique. La livraison se lit dans
        // le texte de l'adresse, jamais dans la zone de la grille : les
        // donnees de demonstration livrent en Belgique avec des grilles
        // etrangeres.
        DB::table('transport_orders')->select('id', 'delivery_address')->orderBy('id')
            ->chunkById(200, function ($lignes) {
                foreach ($lignes as $ligne) {
                    $code = Pays::depuisNom(Adresse::pays((string) $ligne->delivery_address)) ?? 'BE';

                    if ($code !== 'BE') {
                        DB::table('transport_orders')->where('id', $ligne->id)->update(['delivery_country' => $code]);
                    }
                }
            });

        DB::statement("ALTER TABLE transport_orders ADD CONSTRAINT transport_orders_pays_format CHECK (pickup_country ~ '^[A-Z]{2}$' AND delivery_country ~ '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE transport_orders ADD CONSTRAINT transport_orders_trajet_bilateral CHECK (pickup_country = 'BE' OR delivery_country = 'BE')");
        DB::statement("ALTER TABLE transport_orders ADD CONSTRAINT transport_orders_pricing_basis_check CHECK (pricing_basis IN ('LANE', 'BACKHAUL'))");
        DB::statement("ALTER TABLE transport_orders ADD CONSTRAINT transport_orders_backhaul_check CHECK (backhaul_order_id IS NULL OR (pricing_basis = 'BACKHAUL' AND backhaul_order_id <> id))");
        DB::statement("CREATE INDEX transport_orders_enlevement_etranger ON transport_orders (status, pickup_date) WHERE pickup_country <> 'BE'");
        DB::statement("CREATE INDEX transport_orders_livraison_etranger ON transport_orders (status, delivery_lat, delivery_lng) WHERE delivery_country <> 'BE'");

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->char('pickup_country', 2)->default('BE')->after('pickup_address');
        });

        DB::table('quote_requests')->select('id', 'pickup_address')->orderBy('id')
            ->chunkById(200, function ($lignes) {
                foreach ($lignes as $ligne) {
                    $code = Pays::depuisNom(Adresse::pays((string) $ligne->pickup_address)) ?? 'BE';

                    if ($code !== 'BE') {
                        DB::table('quote_requests')->where('id', $ligne->id)->update(['pickup_country' => $code]);
                    }
                }
            });

        // Deux adresses etrangeres depassent vite 255 caracteres.
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transport_orders_enlevement_etranger');
        DB::statement('DROP INDEX IF EXISTS transport_orders_livraison_etranger');

        foreach (['pays_format', 'trajet_bilateral', 'pricing_basis_check', 'backhaul_check'] as $contrainte) {
            DB::statement("ALTER TABLE transport_orders DROP CONSTRAINT IF EXISTS transport_orders_{$contrainte}");
        }

        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backhaul_order_id');
            $table->dropColumn(['pickup_country', 'delivery_country', 'pricing_basis', 'approche_km', 'shipper_name', 'shipper_phone', 'loading_reference']);
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn('pickup_country');
        });

        DB::statement('UPDATE invoice_lines SET description = left(description, 255)');

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('description', 255)->change();
        });
    }
};
