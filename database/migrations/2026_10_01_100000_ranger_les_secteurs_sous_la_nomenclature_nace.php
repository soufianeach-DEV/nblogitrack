<?php

use App\Support\Secteurs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les secteurs de la liste courte d'avant passent sous leur division de
 * la nomenclature NACE, desormais la seule liste proposee.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Secteurs::ANCIENS as $ancien => $nouveau) {
            DB::table('clients')->where('business_sector', $ancien)->update(['business_sector' => $nouveau]);
            DB::table('quote_requests')->where('sector', $ancien)->update(['sector' => $nouveau]);
        }
    }

    public function down(): void
    {
        // Plusieurs anciens secteurs menent au meme secteur NACE : le
        // retour en arriere n'est pas univoque, les valeurs restent.
    }
};
