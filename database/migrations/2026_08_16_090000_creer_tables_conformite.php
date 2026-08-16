<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qui prouve la conformite, plutot que ce qui l'affirme.
 *
 * Deux registres distincts.
 *
 * Le premier retient qu'un conducteur a pris connaissance de la note
 * d'information, et a quelle date. Ce n'est pas un consentement : dans
 * une relation de travail, le consentement n'est pas librement donne et
 * ne vaut pas base legale. Ce qui se prouve ici, c'est la transparence
 * des articles 12 et 13, pas un accord.
 *
 * La version est la date de derniere modification de la note. Si
 * l'administration reecrit le texte, les accuses precedents ne valent
 * plus pour le nouveau : chacun doit reprendre connaissance.
 *
 * Le second est le registre des traitements de l'article 30. Il vit
 * dans l'application et non dans un tableur oublie sur un disque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_acknowledgements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // La date de modification de la note au moment de l'accuse.
            $table->timestamp('version');
            $table->timestamp('acknowledged_at');

            // L'adresse d'ou l'accuse a ete donne : elle situe l'acte sans
            // rien apprendre de plus sur la personne.
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            // Un accuse par conducteur et par version.
            $table->unique(['user_id', 'version']);
        });

        Schema::create('processing_records', function (Blueprint $table) {
            $table->id();

            $table->string('nom');
            $table->unsignedSmallInteger('rang')->default(0);

            $table->text('finalite');
            $table->string('base_legale');
            $table->text('personnes');
            $table->text('donnees');
            $table->text('destinataires');
            $table->text('conservation');
            $table->text('mesures');

            // Un transfert hors Union se declare ; l'absence de transfert
            // se declare aussi, sinon la case vide reste ambigue.
            $table->text('transferts')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_records');
        Schema::dropIfExists('driver_acknowledgements');
    }
};
