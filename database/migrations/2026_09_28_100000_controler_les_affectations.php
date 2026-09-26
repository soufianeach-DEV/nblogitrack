<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que l'affectation doit pouvoir verifier et que rien ne disait :
 * le permis qu'exige chaque vehicule (un tracteur de 44 t type « Frigo »
 * passait avec un permis C), son equipement ADR, et la fin de validite du
 * certificat ADR du chauffeur (cinq ans).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('permis_requis', 3)->nullable()->after('vehicle_type');
            $table->boolean('adr_equipe')->default(false)->after('has_tail_lift');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->date('adr_expiry')->nullable()->after('adr_certified');
        });

        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_permis_requis_check CHECK (permis_requis IS NULL OR permis_requis IN ('B', 'C1', 'C1E', 'C', 'CE'))");

        // Le permis requis se deduit du gabarit : une semi-remorque, ou une
        // charge utile de tracteur (le parc n'a rien entre 14 et 24 t),
        // exige le CE ; au-dela de 3,5 t de charge utile, le C.
        DB::statement(<<<'SQL'
            UPDATE vehicles SET permis_requis = CASE
                WHEN vehicle_type = 'Semi-remorque' OR capacity_tonnes >= 18 THEN 'CE'
                WHEN capacity_tonnes > 3.5 THEN 'C'
                WHEN capacity_tonnes > 1.5 THEN 'C1'
                ELSE 'B'
            END
        SQL);

        // Les citernes transportent carburants et produits chimiques : elles
        // sont equipees ADR. Les autres le seront a la demande, depuis la
        // fiche du vehicule.
        DB::table('vehicles')->where('vehicle_type', 'Citerne')->update(['adr_equipe' => true]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS vehicles_permis_requis_check');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['permis_requis', 'adr_equipe']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('adr_expiry');
        });
    }
};
