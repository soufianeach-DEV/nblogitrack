<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La version d'une page (celle que le chauffeur accepte) suivait
 * updated_at : publier, ranger ou deplacer la note au pied du site en
 * faisait une nouvelle version, sans un mot de change. Tous les
 * conducteurs devaient la reaccepter et leur suivi s'arretait.
 * La date de la derniere modification du titre ou du texte la remplace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->timestamp('contenu_modifie_le')->nullable();
        });

        DB::table('pages')->update(['contenu_modifie_le' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('contenu_modifie_le');
        });
    }
};
