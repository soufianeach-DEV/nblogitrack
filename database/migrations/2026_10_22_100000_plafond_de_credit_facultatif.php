<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Le plafond de credit bloque desormais les commandes : 0, valeur par
    // defaut a l'inscription, aurait bloque toute nouvelle entreprise.
    // Sans plafond, la colonne reste vide.
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('credit_limit', 10, 2)->nullable()->default(null)->change();
        });

        DB::table('clients')->where('credit_limit', 0)->update(['credit_limit' => null]);
    }

    public function down(): void
    {
        DB::table('clients')->whereNull('credit_limit')->update(['credit_limit' => 0]);

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('credit_limit', 10, 2)->default(0)->nullable(false)->change();
        });
    }
};
