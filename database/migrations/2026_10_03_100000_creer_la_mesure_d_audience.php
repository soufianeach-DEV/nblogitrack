<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesure d'audience propre au site, avec l'accord du visiteur : une ligne
 * par page vue ou par conversion (devis envoye, inscription, simulation).
 * Ni temoin de suivi, ni adresse IP, ni identifiant : rien ne relie deux
 * lignes au meme visiteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->date('jour')->index();
            $table->string('chemin', 255);
            $table->char('langue', 2);
            // Premiere page d'une visite (arrivee depuis l'exterieur).
            $table->boolean('entree')->default(false);
            $table->string('source', 100)->nullable();
            $table->string('support', 50)->nullable();
            $table->string('campagne', 100)->nullable();
            $table->string('appareil', 10);
            // devis, inscription, simulation ; null pour une page vue.
            $table->string('evenement', 30)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
