<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Une cle sans entreprise est une cle interne : elle lit les expeditions
 * de tout le monde. La contrainte d'origine vidait client_id quand
 * l'entreprise disparaissait, si bien qu'un partenaire qui supprimait son
 * compte transformait sa propre cle, toujours valide, en cle interne.
 *
 * La cle part desormais avec l'entreprise. Rien ne peut plus fabriquer
 * une cle interne, sauf l'administrateur qui la cree expres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }
};
