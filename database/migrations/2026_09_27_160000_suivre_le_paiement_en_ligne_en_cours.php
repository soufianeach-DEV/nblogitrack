<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * La derniere session de paiement Stripe d'une facture, et la date a
     * laquelle un paiement differe (virement SEPA) a ete lance. Le client
     * ne peut plus payer deux fois la meme facture : deux onglets
     * reprennent la meme session, et un paiement en cours bloque le
     * suivant.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('stripe_session_id', 120)->nullable();
            $table->timestamp('online_payment_pending_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['stripe_session_id', 'online_payment_pending_at']);
        });
    }
};
