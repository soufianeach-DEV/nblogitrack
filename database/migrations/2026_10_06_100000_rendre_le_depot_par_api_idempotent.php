<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Un partenaire qui renvoie son appel apres une coupure reseau creait une
 * seconde expedition. La cle d'idempotence qu'il transmet (en-tete
 * Idempotency-Key) est gardee avec l'ordre : le meme envoi rend l'ordre
 * deja cree. L'index unique tient meme sous deux appels simultanes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable();
            $table->unique(['client_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
