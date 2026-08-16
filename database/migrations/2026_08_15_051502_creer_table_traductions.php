<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les traductions vivent en base et non dans des fichiers lang.
 *
 * L'exigence A11 demande que l'administrateur puisse les administrer.
 * Un fichier PHP n'est pas modifiable depuis une page web sans donner
 * un droit d'ecriture sur le code, ce qu'aucun hebergement serieux
 * n'accorde. Une table se modifie, se journalise et se sauvegarde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();

            // La cle porte son groupe en prefixe : nav.tableau_de_bord.
            // Le groupe sert a decouper l'ecran d'administration.
            $table->string('cle')->unique();
            $table->string('groupe')->index();

            $table->text('fr');
            $table->text('nl')->nullable();
            $table->text('en')->nullable();

            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // La langue choisie par l'utilisateur. Un visiteur non connecte
            // garde la sienne en session.
            $table->string('locale', 2)->default('fr')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::dropIfExists('translations');
    }
};
