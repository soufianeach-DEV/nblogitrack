<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A13 : le contenu des pages publiques, modifiable sans toucher au code.
 *
 * Les conditions generales et la politique de confidentialite changent
 * pour des raisons juridiques, pas techniques. Les laisser dans un
 * composant React imposerait une livraison a chaque virgule corrigee par
 * un juriste.
 *
 * Le corps est enregistre par langue, comme les traductions : une page
 * legale n'est pas une phrase d'interface, elle se redige entierement
 * dans chaque langue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            // L'adresse publique : /fr/p/a-propos. Elle ne change pas quand
            // le titre change, sinon les liens exterieurs se cassent.
            $table->string('slug')->unique();

            $table->string('titre_fr');
            $table->string('titre_nl')->nullable();
            $table->string('titre_en')->nullable();

            $table->text('corps_fr');
            $table->text('corps_nl')->nullable();
            $table->text('corps_en')->nullable();

            // Une page se prepare avant d'etre visible : un brouillon
            // repond 404 au visiteur et reste lisible par l'administrateur.
            $table->boolean('publiee')->default(false);
            $table->timestamp('publiee_le')->nullable();

            // Les pages legales se montrent en pied de page, les autres non.
            $table->boolean('au_pied')->default(false);
            $table->unsignedSmallInteger('rang')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('page_documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');

            // Le nom d'origine est affiche, le chemin est genere : un nom
            // de fichier venu du navigateur ne sert jamais de chemin.
            $table->string('nom_origine');
            $table->string('chemin');
            $table->string('mime', 100);
            $table->unsignedBigInteger('taille');

            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_documents');
        Schema::dropIfExists('pages');
    }
};
