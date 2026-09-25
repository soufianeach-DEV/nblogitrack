<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Le client annule lui-meme une expedition pas encore chargee. Annulee
 * alors qu'un camion lui etait deja reserve, elle porte une indemnite
 * (article 8 bis des conditions generales), facturee le mois de
 * l'annulation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('cancellation_fee', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_fee']);
        });
    }
};
