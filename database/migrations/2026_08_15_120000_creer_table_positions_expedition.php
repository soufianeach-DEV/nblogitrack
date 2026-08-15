<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le suivi geolocalise d'un envoi.
 *
 * Deux natures de point cohabitent ici, et la distinction n'est pas
 * technique, elle est juridique.
 *
 * Un JALON est un fait de gestion : ou et quand la marchandise a ete
 * prise en charge, ou et quand elle a ete livree. Il se conserve comme
 * une mention sur une lettre de voiture.
 *
 * Un point de ROUTE est une position intermediaire, relevee pendant le
 * trajet. Localiser un camion revient a localiser son conducteur : c'est
 * une donnee sur un travailleur. Elle n'est relevee que si le suivi
 * direct est active pour la mission, uniquement pendant qu'elle est en
 * cours, et elle s'efface peu apres la livraison.
 *
 * C'est pourquoi les deux vivent dans la meme table mais ne suivent pas
 * la meme duree de conservation : la purge ne vise que les ROUTE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transport_order_id')->constrained()->cascadeOnDelete();

            // Qui conduisait. Sert a repondre a une demande d'acces d'un
            // conducteur, jamais a construire un ecran par conducteur.
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 6);
            $table->string('evenement', 20)->nullable();

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            // La precision annoncee par le navigateur. Un point a deux
            // kilometres pres ne se presente pas comme une certitude.
            $table->unsignedInteger('precision_m')->nullable();

            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['transport_order_id', 'recorded_at']);
            $table->index(['type', 'recorded_at']);
        });

        Schema::table('transport_orders', function (Blueprint $table) {
            // Le suivi direct est ferme par defaut. Il s'ouvre mission par
            // mission, jamais pour toute la flotte d'un coup : une
            // fonctionnalite activee partout par defaut n'est plus une
            // exception, c'est une surveillance.
            $table->boolean('suivi_direct')->default(false)->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropColumn('suivi_direct');
        });

        Schema::dropIfExists('shipment_positions');
    }
};
