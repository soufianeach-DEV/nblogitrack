<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A12 : l'API REST et ce qui la garde.
 *
 * Une cle n'est jamais enregistree en clair. Si la base fuit, les cles
 * volees ne servent a rien : on ne conserve que leur empreinte, comme
 * pour un mot de passe. C'est aussi pourquoi l'ecran d'administration
 * ne peut plus la reafficher apres sa creation.
 *
 * Le prefixe, lui, est en clair : il permet de reconnaitre une cle dans
 * une liste ou dans un journal sans jamais exposer le secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Les huit premiers caracteres, lisibles. Uniques : ils servent
            // a retrouver la cle sans parcourir toute la table.
            $table->string('prefix', 12)->unique();
            $table->string('token_hash', 64);

            // Le partenaire pour qui la cle agit. Une cle rattachee a une
            // entreprise ne voit que les expeditions de cette entreprise ;
            // sans rattachement, elle est interne et voit tout.
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // Permissions : lecture, ecriture. Une cle de lecture seule ne
            // peut pas creer d'ordre, meme si elle connait l'adresse.
            $table->json('abilities');

            // Restriction par adresse IP. Vide = aucune restriction, ce qui
            // est un choix explicite de l'administrateur, pas un oubli.
            $table->json('allowed_ips')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('requests_count')->default(0);

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();

            // Nul quand la cle presentee est inconnue : le refus se
            // journalise quand meme, c'est meme le cas le plus interessant
            // pour reperer une tentative.
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->cascadeOnDelete();

            $table->string('method', 8);
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Le motif quand l'appel est refuse : cle inconnue, revoquee,
            // expiree, adresse non autorisee, permission absente.
            $table->string('refus')->nullable();

            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
        Schema::dropIfExists('api_keys');
    }
};
